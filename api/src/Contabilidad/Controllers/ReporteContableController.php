<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\ConsultarCorteContableRequest;
use App\Contabilidad\Requests\ConsultarPeriodoContableRequest;
use App\Contabilidad\Requests\ExportarBalancePruebaRequest;
use App\Contabilidad\Requests\ExportarEstadosFinancierosRequest;
use App\Contabilidad\Requests\ExportarLibroDiarioRequest;
use App\Contabilidad\Requests\ExportarLibroMayorRequest;
use App\Contabilidad\Requests\ImprimirLibroMayorRequest;
use App\Contabilidad\Services\AccesoLibroMayor;
use App\Contabilidad\Services\ConciliacionAperturaService;
use App\Contabilidad\Services\InformeContablePdfService;
use App\Contabilidad\Services\ReporteContableService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Http\Controllers\Controller;
use App\Impresion\Data\PdfGenerado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReporteContableController extends Controller
{
    public function __construct(
        protected ReporteContableService $reportes,
        protected InformeContablePdfService $informesPdf,
        protected ConciliacionAperturaService $conciliacion,
        protected AccesoLibroMayor $accesoLibroMayor,
    ) {}

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
        $this->accesoLibroMayor->exigir($peticion->user(), $peticion->integer('cuenta_id'));

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

    public function conciliacionApertura(ConsultarCorteContableRequest $peticion): JsonResponse
    {
        return response()->json($this->conciliacion->conciliar(
            (int) $peticion->user()->empresa_id,
            $peticion->validated('hasta'),
        ));
    }

    public function pdfBalancePrueba(ConsultarCorteContableRequest $peticion): Response
    {
        return $this->pdf($this->informesPdf->balancePrueba($peticion->validated('hasta')));
    }

    public function pdfLibroDiario(ConsultarPeriodoContableRequest $peticion): Response
    {
        return $this->pdf($this->informesPdf->libroDiario($peticion->validated('desde'), $peticion->validated('hasta')));
    }

    public function pdfLibroMayor(ImprimirLibroMayorRequest $peticion): Response
    {
        $this->accesoLibroMayor->exigir($peticion->user(), $peticion->integer('cuenta_id'));

        return $this->pdf($this->informesPdf->libroMayor(
            $peticion->integer('cuenta_id'),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
        ));
    }

    public function pdfEstadosFinancieros(ConsultarPeriodoContableRequest $peticion): Response
    {
        return $this->pdf($this->informesPdf->estadosFinancieros($peticion->validated('desde'), $peticion->validated('hasta')));
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
        $this->accesoLibroMayor->exigir($peticion->user(), $peticion->integer('cuenta_id'));

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

    private function pdf(PdfGenerado $pdf): Response
    {
        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
