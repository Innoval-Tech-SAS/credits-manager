<?php

namespace App\Monitoreo\Services;

use App\Auditoria\Services\EmisorAuditoria;
use App\Monitoreo\Constants\TareaProgramadaConstants;
use App\Monitoreo\Models\TareaProgramada;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use App\Monitoreo\Repositories\TareaProgramadaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class TareaProgramadaService
{
    public function __construct(
        protected TareaProgramadaRepository $tareas,
        protected EjecucionTareaProgramadaRepository $ejecuciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $tareas = $this->tareas->todas();
        $ultimas = $this->ejecuciones->ultimasPorClave($tareas->pluck('clave')->all());

        return $tareas
            ->map(fn (TareaProgramada $tarea) => [
                'clave' => $tarea->clave,
                'hora' => substr($tarea->hora, 0, 5),
                'activa' => $tarea->activa,
                'pausable' => $tarea->clave !== TareaProgramadaConstants::CLAVE_NO_PAUSABLE,
                'ultima_ejecucion' => $ultimas[$tarea->clave] ?? null,
            ])
            ->values()
            ->all();
    }

    public function actualizar(string $clave, string $hora, bool $activa): TareaProgramada
    {
        $tarea = $this->tareas->buscarPorClaveOFallar($clave);
        $anteriores = $this->auditoria->instantanea($tarea);

        $tarea->hora = $hora;
        $tarea->activa = $activa;
        $tarea->save();

        Cache::forget(TareaProgramadaConstants::CACHE_HORARIOS);

        $this->auditoria->registrarModificacion('tareas_programadas', "Tarea programada actualizada: {$clave}", $tarea, $anteriores);

        return $tarea;
    }

    public function historial(string $clave, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $this->tareas->buscarPorClaveOFallar($clave);

        return $this->ejecuciones->paginarPorClave($clave, $porPagina, $pagina);
    }
}
