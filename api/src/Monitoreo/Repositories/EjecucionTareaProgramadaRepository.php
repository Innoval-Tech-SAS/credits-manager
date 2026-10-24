<?php

namespace App\Monitoreo\Repositories;

use App\Core\Base\BaseRepository;
use App\Monitoreo\Models\EjecucionTareaProgramada;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EjecucionTareaProgramadaRepository extends BaseRepository
{
    public function __construct(EjecucionTareaProgramada $modelo)
    {
        parent::__construct($modelo);
    }

    public function registrar(string $clave, string $resultado, CarbonInterface $iniciadoEn, CarbonInterface $finalizadoEn, ?string $error): EjecucionTareaProgramada
    {
        /** @var EjecucionTareaProgramada */
        return $this->crear([
            'tarea_clave' => $clave,
            'iniciado_en' => $iniciadoEn,
            'finalizado_en' => $finalizadoEn,
            'resultado' => $resultado,
            'error' => $error,
        ]);
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, array{resultado: string, finalizado_en: string}> última ejecución por clave
     */
    public function ultimasPorClave(array $claves): array
    {
        return $this->consulta()
            ->whereIn('tarea_clave', $claves)
            ->whereIn('id', function ($sub) use ($claves) {
                $sub->selectRaw('MAX(id)')
                    ->from('ejecuciones_tareas_programadas')
                    ->whereIn('tarea_clave', $claves)
                    ->groupBy('tarea_clave');
            })
            ->get()
            ->keyBy('tarea_clave')
            ->map(fn (EjecucionTareaProgramada $ejecucion) => [
                'resultado' => $ejecucion->resultado,
                'finalizado_en' => $ejecucion->finalizado_en->toIso8601String(),
            ])
            ->all();
    }

    public function paginarPorClave(string $clave, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->where('tarea_clave', $clave)
            ->orderByDesc('iniciado_en')
            ->paginate($porPagina, ['*'], 'page', $pagina);
    }

    public function contarErroresDelMes(): int
    {
        return $this->consulta()
            ->where('resultado', 'error')
            ->whereBetween('iniciado_en', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }
}
