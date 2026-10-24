<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Requests\CrearPlanCuentaRequest;
use App\Contabilidad\Requests\EditarPlanCuentaRequest;
use App\Contabilidad\Services\PlanCuentaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanCuentaController extends Controller
{
    public function __construct(protected PlanCuentaService $cuentas) {}

    public function index(Request $peticion): JsonResponse
    {
        $busqueda = $peticion->string('search')->toString();

        return response()->json(['cuentas' => $this->cuentas->listar($busqueda ?: null)]);
    }

    public function store(CrearPlanCuentaRequest $peticion): JsonResponse
    {
        $cuenta = $this->cuentas->crear($peticion->validated());

        return response()->json(['cuenta' => $cuenta], 201);
    }

    public function update(EditarPlanCuentaRequest $peticion, PlanCuenta $cuenta): JsonResponse
    {
        $cuenta = $this->cuentas->editar($cuenta, $peticion->validated());

        return response()->json(['cuenta' => $cuenta]);
    }
}
