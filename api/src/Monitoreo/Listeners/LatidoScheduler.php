<?php

namespace App\Monitoreo\Listeners;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use App\Monitoreo\Services\RegistroLatidos;
use Illuminate\Console\Events\CommandStarting;

/**
 * `schedule:work` arranca un `schedule:run` cada minuto: ese arranque es el latido del scheduler.
 * Así no hace falta una tarea programada extra que llene el log del servicio cada minuto.
 */
class LatidoScheduler
{
    public function __construct(protected RegistroLatidos $latidos) {}

    public function handle(CommandStarting $evento): void
    {
        if ($evento->command !== 'schedule:run') {
            return;
        }

        $this->latidos->registrar(
            MonitoreoConstants::SERVICIO_SCHEDULER,
            EstadoServicio::Activo,
            null,
            MonitoreoConstants::VIGENCIA_LATIDO_SCHEDULER_SEGUNDOS,
        );
    }
}
