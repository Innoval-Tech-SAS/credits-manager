<?php

namespace App\Dashboard\Repositories;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Models\BitacoraAuditoria;
use App\Empresas\Models\Empresa;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas de plataforma, entre todas las empresas: `BitacoraAuditoria` no lleva `EmpresaScope` (ver su
 * propio modelo), `sessions` es una tabla sin Eloquent de por medio y `Empresa` es la raíz del tenant
 * (no tiene `empresa_id` propio) — ninguna necesita `withoutGlobalScopes()`, pero igual quedan aquí y no
 * sueltas en el Service, por la regla de "una lectura cruzada, un método de Repository explícito".
 */
class PanelSuperadminRepository
{
    public function contarLoginsFallidosDesde(CarbonInterface $desde): int
    {
        return BitacoraAuditoria::query()
            ->where('accion', AccionAuditoria::LoginFallido->value)
            ->where('fecha_hora', '>=', $desde)
            ->count();
    }

    /** Sesiones con actividad dentro de la vigencia configurada (`session.lifetime`), por usuario distinto. */
    public function contarUsuariosActivos(): int
    {
        $umbral = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $umbral)
            ->distinct()
            ->count('user_id');
    }

    public function contarEmpresasActivas(): int
    {
        return Empresa::query()->count();
    }

    /** @return Collection<int, BitacoraAuditoria> filas con `fecha`/`accion`/`total`, una por día y acción con al menos un evento. */
    public function tendenciaLoginsPorDia(CarbonInterface $desde): Collection
    {
        return BitacoraAuditoria::query()
            ->selectRaw('DATE(fecha_hora) as fecha, accion, COUNT(*) as total')
            ->whereIn('accion', [AccionAuditoria::Login->value, AccionAuditoria::LoginFallido->value])
            ->where('fecha_hora', '>=', $desde)
            ->groupBy('fecha', 'accion')
            ->get();
    }

    /** @return Collection<int, Empresa> */
    public function empresasRecientes(int $limite): Collection
    {
        return Empresa::query()->latest('creado_en')->limit($limite)->get(['id', 'nombre_empresa', 'creado_en']);
    }
}
