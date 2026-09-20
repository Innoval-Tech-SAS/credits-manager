<?php

namespace App\Gastos\Controllers;

use App\Gastos\Requests\GuardarValorUvtRequest;
use App\Gastos\Services\ValorUvtService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ValorUvtController extends Controller
{
    public function __construct(protected ValorUvtService $valoresUvt) {}

    public function index(): JsonResponse
    {
        return response()->json(['valores_uvt' => $this->valoresUvt->listar()]);
    }

    public function store(GuardarValorUvtRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        return response()->json(['valor_uvt' => $this->valoresUvt->guardar($datos['anio'], $datos['valor'])], 201);
    }
}
