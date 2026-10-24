<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Requests\ReabrirPeriodoRequest;
use App\Contabilidad\Services\PeriodoContableService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PeriodoContableController extends Controller
{
    public function __construct(protected PeriodoContableService $periodos) {}

    public function index(): JsonResponse
    {
        return response()->json(['periodos' => $this->periodos->listar()]);
    }

    public function cerrar(PeriodoContable $periodo): JsonResponse
    {
        return response()->json(['periodo' => $this->periodos->cerrar($periodo)]);
    }

    public function reabrir(ReabrirPeriodoRequest $peticion, PeriodoContable $periodo): JsonResponse
    {
        $periodo = $this->periodos->reabrir($periodo, $peticion->validated()['motivo']);

        return response()->json(['periodo' => $periodo]);
    }
}
