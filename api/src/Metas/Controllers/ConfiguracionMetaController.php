<?php

namespace App\Metas\Controllers;

use App\Http\Controllers\Controller;
use App\Metas\Requests\ActualizarConfiguracionMetasRequest;
use App\Metas\Services\ConfiguracionMetasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionMetaController extends Controller
{
    public function __construct(protected ConfiguracionMetasService $configuracion) {}

    public function show(Request $peticion): JsonResponse
    {
        return response()->json(['parametros' => $this->configuracion->parametros((int) $peticion->user()->empresa_id)->aArray()]);
    }

    public function update(ActualizarConfiguracionMetasRequest $peticion): JsonResponse
    {
        $parametros = $this->configuracion->guardar($peticion->user(), $peticion->validated());

        return response()->json(['parametros' => $parametros->aArray()]);
    }
}
