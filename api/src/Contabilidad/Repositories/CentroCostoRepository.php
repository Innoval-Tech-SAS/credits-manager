<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\CentroCosto;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CentroCostoRepository extends BaseRepository
{
    public function __construct(CentroCosto $centroCosto)
    {
        parent::__construct($centroCosto);
    }

    public function activos(): Collection
    {
        return $this->consulta()->where('activo', true)->orderBy('nombre')->get();
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->orderBy('codigo');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%");
            });
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }
}
