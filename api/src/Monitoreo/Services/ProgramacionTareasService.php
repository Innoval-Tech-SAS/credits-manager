<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\TareaProgramadaConstants;
use App\Monitoreo\Models\TareaProgramada;
use App\Monitoreo\Repositories\TareaProgramadaRepository;
use Throwable;

/**
 * Lee el catálogo directamente desde la base cada vez para que una pausa o cambio de hora en la tabla
 * se respete sin depender de un caché obsoleto; si la base falla, usa las horas por defecto.
 */
class ProgramacionTareasService
{
    public function __construct(protected TareaProgramadaRepository $tareas) {}

    /** @return array<string, array{hora: string, activa: bool}> */
    public function horariosVigentes(): array
    {
        try {
            return $this->tareas->todas()
                ->mapWithKeys(fn (TareaProgramada $tarea) => [
                    $tarea->clave => ['hora' => substr($tarea->hora, 0, 5), 'activa' => $tarea->activa],
                ])
                ->all();
        } catch (Throwable) {
            return array_map(
                fn (string $hora) => ['hora' => $hora, 'activa' => true],
                TareaProgramadaConstants::HORAS_POR_DEFECTO,
            );
        }
    }
}
