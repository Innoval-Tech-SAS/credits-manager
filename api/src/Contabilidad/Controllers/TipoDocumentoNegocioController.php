<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\TipoDocumentoNegocio;
use App\Contabilidad\Requests\ActualizarTipoDocumentoNegocioRequest;
use App\Contabilidad\Services\TipoDocumentoNegocioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TipoDocumentoNegocioController extends Controller
{
    public function __construct(protected TipoDocumentoNegocioService $tipos) {}

    public function index(): JsonResponse
    {
        return response()->json(['tipos' => $this->tipos->listar(Auth::user()->empresa_id)]);
    }

    public function update(ActualizarTipoDocumentoNegocioRequest $peticion, TipoDocumentoNegocio $tipoDocumentoNegocio): JsonResponse
    {
        $tipo = $this->tipos->actualizar($tipoDocumentoNegocio, $peticion->validated());

        return response()->json(['tipo' => $tipo]);
    }
}
