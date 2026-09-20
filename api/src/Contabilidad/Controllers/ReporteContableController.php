<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Services\ReporteContableService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteContableController extends Controller
{
    public function __construct(protected ReporteContableService $reportes) {}

    public function libroDiario(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->libroDiario(
            $peticion->string('desde', now()->startOfMonth()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function libroMayor(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->libroMayor(
            $peticion->integer('cuenta_id'),
            $peticion->string('desde', now()->startOfYear()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function balancePrueba(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->balancePrueba(
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function estadosFinancieros(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->estadosFinancieros(
            $peticion->string('desde', now()->startOfYear()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }
}
