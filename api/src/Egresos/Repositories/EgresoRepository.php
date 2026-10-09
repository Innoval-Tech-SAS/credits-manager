<?php

namespace App\Egresos\Repositories;

use App\Core\Base\BaseRepository;
use App\Egresos\Models\Egreso;
use Illuminate\Pagination\LengthAwarePaginator;

class EgresoRepository extends BaseRepository
{
    public function __construct(Egreso $egreso)
    {
        parent::__construct($egreso);
    }

    /** @param  array{tercero_id: ?int, desde: ?string, hasta: ?string}  $filtros */
    public function paginarFiltrado(array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()
            ->with(['tercero', 'cuentaCaja:id,codigo,nombre', 'creador:id,nombre'])
            ->when($filtros['tercero_id'] !== null, fn ($query) => $query->where('tercero_id', $filtros['tercero_id']))
            ->when($filtros['desde'] !== null, fn ($query) => $query->whereDate('fecha', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== null, fn ($query) => $query->whereDate('fecha', '<=', $filtros['hasta']))
            ->orderByDesc('consecutivo');

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }
}
