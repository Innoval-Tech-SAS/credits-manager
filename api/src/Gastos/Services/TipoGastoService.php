<?php

namespace App\Gastos\Services;

use App\Gastos\Models\TipoGasto;
use App\Gastos\Repositories\TipoGastoRepository;
use Illuminate\Database\Eloquent\Collection;

class TipoGastoService
{
    public function __construct(protected TipoGastoRepository $tiposGasto) {}

    public function listar(): Collection
    {
        return $this->tiposGasto->todos();
    }

    public function crear(array $datos): TipoGasto
    {
        return $this->tiposGasto->crear($datos);
    }

    public function editar(TipoGasto $tipoGasto, array $datos): TipoGasto
    {
        return $this->tiposGasto->actualizar($tipoGasto, $datos);
    }
}
