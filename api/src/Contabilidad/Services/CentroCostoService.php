<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Repositories\CentroCostoRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class CentroCostoService
{
    public function __construct(protected CentroCostoRepository $centros) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->centros->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): CentroCosto
    {
        return $this->centros->crear($datos);
    }

    public function editar(CentroCosto $centro, array $datos): CentroCosto
    {
        return $this->centros->actualizar($centro, $datos);
    }
}
