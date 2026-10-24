<?php

namespace App\Geografia\Repositories;

use App\Core\Base\BaseRepository;
use App\Geografia\Constants\GeografiaConstants;
use App\Geografia\Models\Municipio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MunicipioRepository extends BaseRepository
{
    public function __construct(Municipio $municipio)
    {
        parent::__construct($municipio);
    }

    public function paginarPorEstado(int $estadoId, bool $soloActivos, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->with('estado:id,pais_id,nombre')
            ->where('estado_id', $estadoId)
            ->when($soloActivos, fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('nombre')
            ->paginate(
                perPage: $porPagina,
                columns: ['id', 'estado_id', 'codigo', 'nombre', 'es_capital', 'activo'],
                page: $pagina,
            );
    }

    /** Con `estado` (id y nombre) para poder mostrar "Municipio — Estado" sin una consulta por fila. */
    public function buscar(?int $estadoId, ?string $texto, bool $soloActivos): Collection
    {
        return $this->consulta()
            ->with('estado:id,pais_id,nombre')
            ->when($estadoId, fn ($consulta) => $consulta->where('estado_id', $estadoId))
            ->when($texto, fn ($consulta) => $consulta->where('nombre', 'like', "%{$texto}%"))
            ->when($soloActivos, fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('nombre')
            ->limit(GeografiaConstants::MAXIMO_MUNICIPIOS_POR_BUSQUEDA)
            ->get(['id', 'estado_id', 'codigo', 'nombre', 'es_capital', 'activo']);
    }
}
