<?php

namespace App\Empresas\Repositories;

use App\Core\Base\BaseRepository;
use App\Empresas\Models\Empresa;
use Illuminate\Pagination\LengthAwarePaginator;

class EmpresaRepository extends BaseRepository
{
    public function __construct(Empresa $empresa)
    {
        parent::__construct($empresa);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->with('municipio:id,nombre')->orderBy('nombre_empresa');

        if ($busqueda) {
            $consulta->where('nombre_empresa', 'like', "%{$busqueda}%");
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }
}
