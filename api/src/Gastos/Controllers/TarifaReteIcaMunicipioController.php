<?php

namespace App\Gastos\Controllers;

use App\Gastos\Requests\CrearTarifaReteIcaMunicipioRequest;
use App\Gastos\Services\TarifaReteIcaMunicipioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TarifaReteIcaMunicipioController extends Controller
{
    public function __construct(protected TarifaReteIcaMunicipioService $tarifas) {}

    public function index(): JsonResponse
    {
        return response()->json(['tarifas' => $this->tarifas->listar()]);
    }

    public function store(CrearTarifaReteIcaMunicipioRequest $peticion): JsonResponse
    {
        return response()->json(['tarifa' => $this->tarifas->crear($peticion->validated())], 201);
    }
}
