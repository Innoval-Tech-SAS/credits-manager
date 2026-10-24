<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Constants\TareaProgramadaConstants;
use App\Monitoreo\Events\EstadoMonitoreoActualizado;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Estado en vivo del panel de salud. La API lo arma al abrir la página; desde ahí, el monitor lo
 * publica por Reverb cada pocos segundos, pero solo mientras alguien tenga el canal abierto.
 */
class PanelMonitoreoService
{
    public function __construct(
        protected MetricasServidorService $metricas,
        protected EstadoServiciosService $servicios,
        protected EstadoColasService $colas,
        protected EjecucionTareaProgramadaRepository $ejecucionesTareas,
    ) {}

    public function instantanea(): array
    {
        return [
            'servidor' => $this->metricas->medir(),
            'servicios' => $this->servicios->revisar(),
            'colas' => $this->colas->colas(),
            'auditoria' => $this->colas->auditoria(),
            'tareas_programadas' => $this->ejecucionesTareas->ultimasPorClave(TareaProgramadaConstants::CLAVES),
            'generado_en' => now()->toIso8601String(),
        ];
    }

    /** @return bool si publicó (había alguien mirando) */
    public function publicarSiHayObservadores(): bool
    {
        if (! $this->hayObservadores()) {
            return false;
        }

        EstadoMonitoreoActualizado::dispatch($this->instantanea());

        return true;
    }

    /**
     * Reverb responde la API HTTP de Pusher: `occupied` dice si el canal tiene suscriptores. Si Reverb
     * no responde no hay a quién publicarle; la página cae a consultar la API por su cuenta.
     */
    private function hayObservadores(): bool
    {
        try {
            $emisor = Broadcast::connection();

            if (! $emisor instanceof PusherBroadcaster) {
                return false;
            }

            return (bool) ($emisor->getPusher()->getChannelInfo('private-'.MonitoreoConstants::CANAL)->occupied ?? false);
        } catch (Throwable) {
            return false;
        }
    }
}
