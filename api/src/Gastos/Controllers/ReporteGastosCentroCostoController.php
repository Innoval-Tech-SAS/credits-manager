<?php

namespace App\Gastos\Controllers;

use App\Exportacion\Enums\FormatoExportacion;
use App\Gastos\Requests\ConsultarReporteGastosCentroCostoRequest;
use App\Gastos\Requests\ExportarReporteGastosCentroCostoRequest;
use App\Gastos\Services\ReporteGastosCentroCostoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReporteGastosCentroCostoController extends Controller
{
    public function __construct(protected ReporteGastosCentroCostoService $reporte) {}

    public function index(ConsultarReporteGastosCentroCostoRequest $peticion): JsonResponse
    {
        return response()->json($this->reporte->resumen(
            (int) $peticion->user()->empresa_id,
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
        ));
    }

    public function exportar(ExportarReporteGastosCentroCostoRequest $peticion): JsonResponse
    {
        $exportacion = $this->reporte->solicitarExportacion(
            $peticion->user(),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }
}
