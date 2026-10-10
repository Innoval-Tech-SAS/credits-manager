<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Requests\ActualizarConfiguracionFacturacionRequest;
use App\Facturacion\Services\ConfiguracionFacturacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionFacturacionController extends Controller
{
    public function __construct(protected ConfiguracionFacturacionService $configuracion) {}

    public function show(Request $peticion): JsonResponse
    {
        return response()->json(['parametros' => $this->configuracion->parametros((int) $peticion->user()->empresa_id)->aArray()]);
    }

    public function update(ActualizarConfiguracionFacturacionRequest $peticion): JsonResponse
    {
        $parametros = $this->configuracion->guardar($peticion->user(), $peticion->validated());

        return response()->json(['parametros' => $parametros->aArray()]);
    }
}
