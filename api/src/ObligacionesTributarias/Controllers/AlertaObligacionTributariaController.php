<?php

namespace App\ObligacionesTributarias\Controllers;

use App\Http\Controllers\Controller;
use App\ObligacionesTributarias\Models\AlertaObligacionTributaria;
use App\ObligacionesTributarias\Services\AlertaObligacionTributariaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertaObligacionTributariaController extends Controller
{
    public function __construct(protected AlertaObligacionTributariaService $alertas) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json(['data' => $this->alertas->listar((int) $peticion->user()->empresa_id)]);
    }

    public function marcarVista(AlertaObligacionTributaria $alerta): JsonResponse
    {
        return response()->json(['alerta' => $this->alertas->marcarVista($alerta)]);
    }
}
