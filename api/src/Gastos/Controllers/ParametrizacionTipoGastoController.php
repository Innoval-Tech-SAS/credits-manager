<?php

namespace App\Gastos\Controllers;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Causacion\Services\ParametrizacionTipoGastoService;
use App\Gastos\Requests\CrearParametrizacionTipoGastoRequest;
use App\Gastos\Requests\EditarParametrizacionTipoGastoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametrizacionTipoGastoController extends Controller
{
    public function __construct(protected ParametrizacionTipoGastoService $parametrizaciones) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json([
            'parametrizaciones' => $this->parametrizaciones->listarPorTipoGasto($peticion->integer('tipo_gasto_id')),
        ]);
    }

    public function show(ParametrizacionTipoGasto $parametrizacion): JsonResponse
    {
        return response()->json(['parametrizacion' => $parametrizacion]);
    }

    public function store(CrearParametrizacionTipoGastoRequest $peticion): JsonResponse
    {
        return response()->json(['parametrizacion' => $this->parametrizaciones->crear($peticion->validated())], 201);
    }

    public function update(EditarParametrizacionTipoGastoRequest $peticion, ParametrizacionTipoGasto $parametrizacion): JsonResponse
    {
        return response()->json(['parametrizacion' => $this->parametrizaciones->editar($parametrizacion, $peticion->validated())]);
    }
}
