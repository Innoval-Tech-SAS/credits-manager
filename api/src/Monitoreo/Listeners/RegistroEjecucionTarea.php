<?php

namespace App\Monitoreo\Listeners;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * `ScheduleRunCommand` dispara `ScheduledTaskFinished` incluso cuando el comando terminó con código de
 * salida distinto de cero (antes de lanzar la excepción que dispara `ScheduledTaskFailed` justo después):
 * aquí se ignora ese caso para no duplicar la fila, `alFallar` ya la registra con el error real.
 */
class RegistroEjecucionTarea
{
    public function __construct(protected EjecucionTareaProgramadaRepository $ejecuciones) {}

    public function alTerminar(ScheduledTaskFinished $evento): void
    {
        if ($evento->task->exitCode !== 0) {
            return;
        }

        $this->registrar($evento->task, 'ok', now()->subSeconds((int) round($evento->runtime)));
    }

    public function alFallar(ScheduledTaskFailed|ScheduledTaskSkipped $evento): void
    {
        if ($evento instanceof ScheduledTaskFailed) {
            $this->registrar($evento->task, 'error', now(), Str::limit($evento->exception->getMessage(), MonitoreoConstants::ERROR_MAXIMO_CARACTERES));

            return;
        }

        $this->registrar($evento->task, 'omitida', now());
    }

    private function registrar(Event $tarea, string $resultado, Carbon $iniciadoEn, ?string $error = null): void
    {
        $clave = $tarea->description;

        if ($clave === null) {
            return;
        }

        try {
            $this->ejecuciones->registrar($clave, $resultado, $iniciadoEn, now(), $error);
        } catch (Throwable) {
            // Nunca interrumpe el scheduler: si falla el registro, la ejecución real igual ocurrió.
        }
    }
}
