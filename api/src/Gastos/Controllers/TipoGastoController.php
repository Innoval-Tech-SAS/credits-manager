<?php

namespace App\Gastos\Controllers;

use App\Gastos\Models\TipoGasto;
use App\Gastos\Requests\CrearTipoGastoRequest;
use App\Gastos\Requests\EditarTipoGastoRequest;
use App\Gastos\Services\TipoGastoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TipoGastoController extends Controller
{
    public function __construct(protected TipoGastoService $tiposGasto) {}

    public function index(): JsonResponse
    {
        return response()->json(['tipos_gasto' => $this->tiposGasto->listar()]);
    }

    public function show(TipoGasto $tipoGasto): JsonResponse
    {
        return response()->json(['tipo_gasto' => [
            ...$tipoGasto->toArray(),
            'tiene_gastos_asociados' => $this->tiposGasto->tieneGastosAsociados($tipoGasto),
        ]]);
    }

    public function store(CrearTipoGastoRequest $peticion): JsonResponse
    {
        return response()->json(['tipo_gasto' => $this->tiposGasto->crear($peticion->validated())], 201);
    }

    public function update(EditarTipoGastoRequest $peticion, TipoGasto $tipoGasto): JsonResponse
    {
        return response()->json(['tipo_gasto' => $this->tiposGasto->editar($tipoGasto, $peticion->validated())]);
    }

    public function destroy(TipoGasto $tipoGasto): Response
    {
        $this->tiposGasto->eliminar($tipoGasto);

        return response()->noContent();
    }
}
