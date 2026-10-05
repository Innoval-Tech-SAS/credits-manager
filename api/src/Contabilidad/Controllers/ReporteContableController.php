<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\ExportarBalancePruebaRequest;
use App\Contabilidad\Requests\ExportarEstadosFinancierosRequest;
use App\Contabilidad\Requests\ExportarLibroDiarioRequest;
use App\Contabilidad\Requests\ExportarLibroMayorRequest;
use App\Contabilidad\Services\ReporteContableService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteContableController extends Controller
{
    public function __construct(protected ReporteContableService $reportes) {}

    public function libroDiario(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->libroDiario(
            (int) $peticion->user()->empresa_id,
            $peticion->string('desde', now()->startOfMonth()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function libroMayor(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->libroMayor(
            (int) $peticion->user()->empresa_id,
            $peticion->integer('cuenta_id'),
            $peticion->string('desde', now()->startOfYear()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function balancePrueba(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->balancePrueba(
            (int) $peticion->user()->empresa_id,
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function estadosFinancieros(Request $peticion): JsonResponse
    {
        return response()->json($this->reportes->estadosFinancieros(
            (int) $peticion->user()->empresa_id,
            $peticion->string('desde', now()->startOfYear()->toDateString())->toString(),
            $peticion->string('hasta', now()->toDateString())->toString(),
        ));
    }

    public function exportarLibroDiario(ExportarLibroDiarioRequest $peticion): JsonResponse
    {
        $exportacion = $this->reportes->solicitarExportacionLibroDiario(
            $peticion->user(),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function exportarLibroMayor(ExportarLibroMayorRequest $peticion): JsonResponse
    {
        $exportacion = $this->reportes->solicitarExportacionLibroMayor(
            $peticion->user(),
            $peticion->integer('cuenta_id'),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function exportarBalancePrueba(ExportarBalancePruebaRequest $peticion): JsonResponse
    {
        $exportacion = $this->reportes->solicitarExportacionBalancePrueba(
            $peticion->user(),
            $peticion->validated('hasta'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function exportarEstadosFinancieros(ExportarEstadosFinancierosRequest $peticion): JsonResponse
    {
        $exportacion = $this->reportes->solicitarExportacionEstadosFinancieros(
            $peticion->user(),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }
}
