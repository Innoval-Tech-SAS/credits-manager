<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EstadoSolicitudReapertura;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\SolicitudReaperturaPeriodo;
use App\Contabilidad\Repositories\SolicitudReaperturaPeriodoRepository;
use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Services\NotificacionService;
use App\Usuarios\Models\Usuario;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El contador cierra un período; para reabrirlo pide autorización. La solicitud, su autorización o
 * rechazo y la reapertura resultante quedan en la bitácora.
 */
class SolicitudReaperturaPeriodoService
{
    public function __construct(
        protected SolicitudReaperturaPeriodoRepository $solicitudes,
        protected PeriodoContableService $periodos,
        protected UsuarioRepository $usuarios,
        protected NotificacionService $notificaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** Quien puede reabrir ve todas las de su empresa; el resto, solo las que él mismo pidió. */
    public function listar(Usuario $usuario): Collection
    {
        return $this->solicitudes->listar($usuario->tienePermiso('contabilidad', 'reabrir_periodo') ? null : $usuario->id);
    }

    public function solicitar(PeriodoContable $periodo, string $motivo, Usuario $solicitante): SolicitudReaperturaPeriodo
    {
        return DB::transaction(function () use ($periodo, $motivo, $solicitante) {
            if ($periodo->estado !== EstadoPeriodo::Cerrado) {
                throw ValidationException::withMessages(['periodo' => __('validation.custom.periodo.no_cerrado')]);
            }

            if ($this->solicitudes->existePendienteDelPeriodo($periodo->id)) {
                throw ValidationException::withMessages(['periodo' => __('validation.custom.periodo.ya_solicitado')]);
            }

            $solicitud = $this->solicitudes->crear([
                'periodo_id' => $periodo->id,
                'solicitado_por' => $solicitante->id,
                'motivo' => $motivo,
                'estado' => EstadoSolicitudReapertura::Pendiente,
            ]);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                "Solicitud de reapertura del período {$periodo->mes}/{$periodo->anio}",
                $solicitud,
            );

            foreach ($this->usuarios->activosConPermiso('contabilidad', 'reabrir_periodo') as $administrador) {
                if ($administrador->id === $solicitante->id) {
                    continue;
                }

                $this->notificaciones->notificar(
                    $administrador->id,
                    $solicitante->empresa_id,
                    TipoNotificacion::ReaperturaSolicitada,
                    'Solicitud de reapertura',
                    "{$solicitante->nombre} pide reabrir el período {$periodo->mes}/{$periodo->anio}: {$motivo}",
                    ['solicitud_id' => $solicitud->id, 'periodo_id' => $periodo->id],
                );
            }

            return $solicitud->load(['periodo:id,anio,mes,estado', 'solicitante:id,nombre']);
        });
    }

    /** Reabre el período con el motivo que dio el solicitante y deja la solicitud como autorizada. */
    public function autorizar(SolicitudReaperturaPeriodo $solicitud, Usuario $administrador): SolicitudReaperturaPeriodo
    {
        return DB::transaction(function () use ($solicitud, $administrador) {
            $solicitud = $this->pendienteBloqueada($solicitud);
            $periodo = $solicitud->periodo;

            if ($periodo->estado !== EstadoPeriodo::Cerrado) {
                throw ValidationException::withMessages(['periodo' => __('validation.custom.periodo.ya_abierto')]);
            }

            $this->periodos->reabrir($periodo, $solicitud->motivo);

            $solicitud = $this->resolver($solicitud, EstadoSolicitudReapertura::Autorizada, $administrador, null, AccionAuditoria::Aprobar);

            $this->avisarAlSolicitante($solicitud, TipoNotificacion::ReaperturaAutorizada, 'Reapertura autorizada', "Se reabrió el período {$periodo->mes}/{$periodo->anio}.");

            return $solicitud;
        });
    }

    public function rechazar(SolicitudReaperturaPeriodo $solicitud, string $motivoResolucion, Usuario $administrador): SolicitudReaperturaPeriodo
    {
        return DB::transaction(function () use ($solicitud, $motivoResolucion, $administrador) {
            $solicitud = $this->pendienteBloqueada($solicitud);
            $periodo = $solicitud->periodo;

            $solicitud = $this->resolver($solicitud, EstadoSolicitudReapertura::Rechazada, $administrador, $motivoResolucion, AccionAuditoria::Rechazar);

            $this->avisarAlSolicitante($solicitud, TipoNotificacion::ReaperturaRechazada, 'Reapertura rechazada', "No se reabrió el período {$periodo->mes}/{$periodo->anio}: {$motivoResolucion}");

            return $solicitud;
        });
    }

    private function pendienteBloqueada(SolicitudReaperturaPeriodo $solicitud): SolicitudReaperturaPeriodo
    {
        $solicitud = $this->solicitudes->bloquear($solicitud->id);

        if ($solicitud->estado !== EstadoSolicitudReapertura::Pendiente) {
            throw ValidationException::withMessages(['solicitud' => __('validation.custom.solicitud.ya_resuelta')]);
        }

        return $solicitud;
    }

    private function resolver(SolicitudReaperturaPeriodo $solicitud, EstadoSolicitudReapertura $estado, Usuario $administrador, ?string $motivoResolucion, AccionAuditoria $accion): SolicitudReaperturaPeriodo
    {
        $anteriores = $this->auditoria->instantanea($solicitud);

        $solicitud = $this->solicitudes->actualizar($solicitud, [
            'estado' => $estado,
            'resuelto_por' => $administrador->id,
            'resuelto_en' => now(),
            'motivo_resolucion' => $motivoResolucion,
        ]);

        $periodo = $solicitud->periodo;
        $descripcion = $estado === EstadoSolicitudReapertura::Autorizada ? 'autorizada' : 'rechazada';

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CONTABILIDAD,
            "Solicitud de reapertura del período {$periodo->mes}/{$periodo->anio} {$descripcion}",
            $solicitud,
            $anteriores,
            $motivoResolucion,
            $accion,
        );

        return $solicitud->load(['periodo:id,anio,mes,estado', 'solicitante:id,nombre', 'resolutor:id,nombre']);
    }

    private function avisarAlSolicitante(SolicitudReaperturaPeriodo $solicitud, TipoNotificacion $tipo, string $titulo, string $mensaje): void
    {
        if ($solicitud->solicitado_por === null) {
            return;
        }

        $this->notificaciones->notificar(
            $solicitud->solicitado_por,
            $solicitud->empresa_id,
            $tipo,
            $titulo,
            $mensaje,
            ['solicitud_id' => $solicitud->id, 'periodo_id' => $solicitud->periodo_id],
        );
    }
}
