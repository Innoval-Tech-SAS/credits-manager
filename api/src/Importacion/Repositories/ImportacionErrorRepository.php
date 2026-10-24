<?php

namespace App\Importacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Importacion\Models\ImportacionError;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class ImportacionErrorRepository extends BaseRepository
{
    public function __construct(ImportacionError $error)
    {
        parent::__construct($error);
    }

    public function paginarDeImportacion(int $importacionId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->porImportacion($importacionId)
            ->orderBy('fila')
            ->orderBy('id')
            ->paginate($porPagina, ['*'], 'page', $pagina);
    }

    /** @return LazyCollection<int, ImportacionError> */
    public function cursorDeImportacion(int $importacionId): LazyCollection
    {
        return $this->porImportacion($importacionId)->orderBy('fila')->orderBy('id')->lazy(500);
    }

    /** Filas con error de validación: la escritura no vuelve a evaluarlas. */
    public function filasRechazadasEnValidacion(int $importacionId): array
    {
        return $this->porImportacion($importacionId)
            ->where('severidad', 'error')
            ->where('fase', 'validacion')
            ->whereNotNull('fila')
            ->distinct()
            ->pluck('fila')
            ->flip()
            ->all();
    }

    /** @param list<array<string, mixed>> $filas */
    public function insertarLote(array $filas): void
    {
        $ahora = Carbon::now();

        foreach (array_chunk($filas, 500) as $bloque) {
            $this->modelo->newQuery()->insert(array_map(fn (array $fila) => [
                ...$fila,
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ], $bloque));
        }
    }

    private function porImportacion(int $importacionId): Builder
    {
        return $this->consulta()->where('importacion_id', $importacionId);
    }
}
