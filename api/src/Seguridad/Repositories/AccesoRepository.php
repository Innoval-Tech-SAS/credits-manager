<?php

namespace App\Seguridad\Repositories;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Models\BitacoraAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lee la misma `BitacoraAuditoria` (sin `EmpresaScope`, ver su modelo) acotada a login/logout/login
 * fallido: no es una tabla nueva, es la excepción cruzada ya declarada para la bitácora, reusada aquí
 * detrás del mismo middleware `superadmin`.
 */
class AccesoRepository
{
    private const ACCIONES = [AccionAuditoria::Login->value, AccionAuditoria::Logout->value, AccionAuditoria::LoginFallido->value];

    /** Mismo umbral que `RateLimiter::for('login', ...)` (`AppServiceProvider`): 5 intentos, con más margen de tiempo. */
    private const INTENTOS_FUERZA_BRUTA = 5;

    private const MINUTOS_VENTANA_FUERZA_BRUTA = 10;

    public function paginar(?string $desde, ?string $hasta, ?int $empresaId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta(
            $desde ? CarbonImmutable::parse($desde)->startOfDay() : null,
            $hasta ? CarbonImmutable::parse($hasta)->endOfDay() : null,
            $empresaId,
        )->paginate($porPagina, page: $pagina);
    }

    public function hayPatronDeFuerzaBruta(): bool
    {
        return BitacoraAuditoria::query()
            ->where('accion', AccionAuditoria::LoginFallido->value)
            ->where('fecha_hora', '>=', now()->subMinutes(self::MINUTOS_VENTANA_FUERZA_BRUTA))
            ->whereNotNull('ip')
            ->select('ip')
            ->groupBy('ip')
            ->havingRaw('COUNT(*) >= ?', [self::INTENTOS_FUERZA_BRUTA])
            ->exists();
    }

    private function consulta(?CarbonImmutable $desde, ?CarbonImmutable $hasta, ?int $empresaId): Builder
    {
        return BitacoraAuditoria::query()
            ->whereIn('accion', self::ACCIONES)
            ->when($desde, fn (Builder $q, CarbonImmutable $d) => $q->where('fecha_hora', '>=', $d->format('Y-m-d H:i:s.v')))
            ->when($hasta, fn (Builder $q, CarbonImmutable $h) => $q->where('fecha_hora', '<=', $h->format('Y-m-d H:i:s.v')))
            ->when($empresaId, fn (Builder $q, int $id) => $q->where('empresa_id', $id))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');
    }
}
