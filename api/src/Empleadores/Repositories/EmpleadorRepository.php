<?php

namespace App\Empleadores\Repositories;

use App\Core\Base\BaseRepository;
use App\Empleadores\Models\Empleador;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EmpleadorRepository extends BaseRepository
{
    public function __construct(Empleador $empleador)
    {
        parent::__construct($empleador);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->orderBy('nombre');

        if ($busqueda) {
            $consulta->where('nombre', 'like', "%{$busqueda}%");
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    public function conClientes(): Collection
    {
        return $this->consulta()
            ->whereHas('clientes')
            ->orderBy('nombre')
            ->get();
    }
}
