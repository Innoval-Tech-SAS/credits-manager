<?php

namespace App\Monitoreo\Listeners;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use App\Monitoreo\Services\RegistroLatidos;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;

/**
 * Latido de los workers de cola. `Looping` llega en cada vuelta del worker (cada segundo con
 * `--sleep=1`) mientras espera; durante un job no hay vueltas, así que se marca "ocupado" al empezar.
 * Cada worker atiende una sola cola y su servicio del compose se llama `{cola}-worker`.
 */
class LatidoTrabajadores
{
    public function __construct(protected RegistroLatidos $latidos) {}

    public function enEspera(Looping $evento): void
    {
        $this->registrar($evento->queue, EstadoServicio::Activo, null);
    }

    public function alEmpezar(JobProcessing $evento): void
    {
        $this->registrar($evento->job->getQueue(), EstadoServicio::Ocupado, $evento->job->resolveName());
    }

    public function alTerminar(JobProcessed|JobFailed $evento): void
    {
        $this->registrar($evento->job->getQueue(), EstadoServicio::Activo, null);
    }

    private function registrar(?string $cola, EstadoServicio $estado, ?string $detalle): void
    {
        if (! in_array($cola, MonitoreoConstants::COLAS, true)) {
            return;
        }

        $this->latidos->registrar($cola.MonitoreoConstants::SUFIJO_WORKER, $estado, $detalle);
    }
}
