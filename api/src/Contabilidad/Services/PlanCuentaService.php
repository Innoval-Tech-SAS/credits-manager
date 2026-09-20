<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use Illuminate\Database\Eloquent\Collection;

class PlanCuentaService
{
    public function __construct(protected PlanCuentaRepository $cuentas) {}

    public function listar(?string $busqueda = null): Collection
    {
        return $this->cuentas->todasOrdenadas($busqueda);
    }

    public function crear(array $datos): PlanCuenta
    {
        return $this->cuentas->crear($datos);
    }

    public function editar(PlanCuenta $cuenta, array $datos): PlanCuenta
    {
        return $this->cuentas->actualizar($cuenta, $datos);
    }
}
