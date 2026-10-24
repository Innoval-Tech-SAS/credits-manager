<?php

namespace App\Monitoreo\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Models\TrabajoFallido;
use App\Monitoreo\Repositories\TrabajoFallidoRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrabajosFallidosService
{
    public function __construct(
        protected TrabajoFallidoRepository $fallidos,
        protected EmisorAuditoria $auditoria,
    ) {}

    /**
     * Sin el payload completo: puede llevar datos de negocio serializados. Del error solo sale la
     * primera línea (sin la traza), recortada.
     */
    public function listar(?string $cola, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->fallidos->paginar($cola, $porPagina, $pagina)
            ->through(fn (TrabajoFallido $trabajo) => [
                'uuid' => $trabajo->uuid,
                'cola' => $trabajo->queue,
                'trabajo' => $this->nombreTrabajo($trabajo),
                'error' => Str::limit(Str::before((string) $trabajo->exception, "\n"), MonitoreoConstants::ERROR_MAXIMO_CARACTERES),
                'fallido_en' => $trabajo->failed_at?->toIso8601String(),
            ]);
    }

    /** `queue:retry` lo devuelve a su cola y lo quita de `failed_jobs` (tabla del framework, no dato de negocio). */
    public function reintentar(Usuario $superadmin, string $uuid): void
    {
        $trabajo = $this->fallidos->buscarPorUuid($uuid) ?? throw new NotFoundHttpException('El trabajo fallido no existe.');

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->auditoria->registrar(
            AccionAuditoria::Reintentar,
            MonitoreoConstants::MODULO,
            'Reintento del trabajo fallido '.$this->nombreTrabajo($trabajo).' de la cola '.$trabajo->queue,
            nuevos: ['uuid' => $uuid, 'cola' => $trabajo->queue],
            autor: $superadmin,
        );
    }

    private function nombreTrabajo(TrabajoFallido $trabajo): string
    {
        return class_basename((string) (json_decode($trabajo->payload, true)['displayName'] ?? 'Desconocido'));
    }
}
