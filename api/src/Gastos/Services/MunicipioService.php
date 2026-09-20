<?php

namespace App\Gastos\Services;

use App\Gastos\Models\Municipio;
use App\Gastos\Repositories\MunicipioRepository;
use Illuminate\Database\Eloquent\Collection;

class MunicipioService
{
    public function __construct(protected MunicipioRepository $municipios) {}

    public function listar(): Collection
    {
        return $this->municipios->todos();
    }

    public function crear(array $datos): Municipio
    {
        return $this->municipios->crear($datos);
    }

    public function editar(Municipio $municipio, array $datos): Municipio
    {
        return $this->municipios->actualizar($municipio, $datos);
    }
}
