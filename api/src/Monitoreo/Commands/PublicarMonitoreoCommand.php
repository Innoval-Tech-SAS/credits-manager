<?php

namespace App\Monitoreo\Commands;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use App\Monitoreo\Services\PanelMonitoreoService;
use App\Monitoreo\Services\RegistroLatidos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proceso de larga duración del servicio `monitor`: late y, si alguien mira el panel, publica el
 * estado por Reverb. Va aparte del scheduler para no llenar su log con una línea cada 5 segundos.
 */
class PublicarMonitoreoCommand extends Command
{
    protected $signature = 'monitoreo:publicar';

    protected $description = 'Publica por Reverb el estado del servidor, los servicios y las colas mientras alguien mira el panel de salud';

    private bool $detener = false;

    public function handle(PanelMonitoreoService $panel, RegistroLatidos $latidos): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            // Igual que el listener de auditoría: siendo PID 1 de la imagen php:fpm, sin esto `stop` acaba en SIGKILL.
            foreach ([SIGTERM, SIGINT, SIGQUIT] as $senal) {
                pcntl_signal($senal, fn () => $this->detener = true);
            }
        }

        Log::info('Monitor de salud iniciado.');

        while (! $this->detener) {
            $inicio = microtime(true);

            $latidos->registrar(MonitoreoConstants::SERVICIO_MONITOR, EstadoServicio::Activo);

            try {
                $panel->publicarSiHayObservadores();
            } catch (Throwable $excepcion) {
                Log::warning('Monitor de salud: no se pudo publicar el estado.', ['error' => $excepcion->getMessage()]);
            }

            $this->esperar(MonitoreoConstants::INTERVALO_PUBLICACION_SEGUNDOS - (microtime(true) - $inicio));
        }

        Log::info('Monitor de salud detenido.');

        return self::SUCCESS;
    }

    /** En pasos cortos, para atender SIGTERM sin esperar la vuelta completa. */
    private function esperar(float $segundos): void
    {
        $hasta = microtime(true) + max(0.0, $segundos);

        while (! $this->detener && microtime(true) < $hasta) {
            usleep(200_000);
        }
    }
}
