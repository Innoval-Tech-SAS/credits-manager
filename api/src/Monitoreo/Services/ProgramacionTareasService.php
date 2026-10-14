<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\TareaProgramadaConstants;
use App\Monitoreo\Models\TareaProgramada;
use App\Monitoreo\Repositories\TareaProgramadaRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lee `routes/console.php` en cada `schedule:run` (uno nuevo por minuto, ver `ScheduleWorkCommand`):
 * si la base no responde, usa las horas por defecto para que el scheduler nunca se quede sin programación.
 */
class ProgramacionTareasService
{
    public function __construct(protected TareaProgramadaRepository $tareas) {}

    /** @return array<string, array{hora: string, activa: bool}> */
    public function horariosVigentes(): array
    {
        try {
            return Cache::remember(TareaProgramadaConstants::CACHE_HORARIOS, 60, function () {
                return $this->tareas->todas()
                    ->mapWithKeys(fn (TareaProgramada $tarea) => [
                        $tarea->clave => ['hora' => substr($tarea->hora, 0, 5), 'activa' => $tarea->activa],
                    ])
                    ->all();
            });
        } catch (Throwable) {
            return array_map(
                fn (string $hora) => ['hora' => $hora, 'activa' => true],
                TareaProgramadaConstants::HORAS_POR_DEFECTO,
            );
        }
    }
}
