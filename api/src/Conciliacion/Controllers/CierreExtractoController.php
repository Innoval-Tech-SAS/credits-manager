<?php

namespace App\Conciliacion\Controllers;

use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Requests\ReabrirExtractoRequest;
use App\Conciliacion\Services\CierreExtractoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CierreExtractoController extends Controller
{
    public function __construct(protected CierreExtractoService $cierre) {}

    public function cerrar(Request $peticion, ExtractoBancario $extracto): JsonResponse
    {
        return response()->json(['extracto' => $this->cierre->cerrar($extracto, $peticion->user())]);
    }

    public function reabrir(ReabrirExtractoRequest $peticion, ExtractoBancario $extracto): JsonResponse
    {
        $extracto = $this->cierre->reabrir($extracto, $peticion->user(), $peticion->validated('motivo'));

        return response()->json(['extracto' => $extracto]);
    }
}
