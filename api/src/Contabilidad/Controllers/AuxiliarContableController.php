<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\ConsultarAuxiliarRequest;
use App\Contabilidad\Requests\ExportarAuxiliarRequest;
use App\Contabilidad\Services\AuxiliarContableService;
use App\Contabilidad\Services\InformeContablePdfService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuxiliarContableController extends Controller
{
    public function __construct(
        protected AuxiliarContableService $auxiliares,
        protected InformeContablePdfService $informesPdf,
    ) {}

    public function index(ConsultarAuxiliarRequest $peticion): JsonResponse
    {
        return response()->json($this->auxiliares->auxiliar(
            (int) $peticion->user()->empresa_id,
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            $peticion->validated('cuenta_id'),
            $peticion->validated('tercero_id'),
        ));
    }

    public function exportar(ExportarAuxiliarRequest $peticion): JsonResponse
    {
        $exportacion = $this->auxiliares->solicitarExportacion(
            $peticion->user(),
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            $peticion->validated('cuenta_id'),
            $peticion->validated('tercero_id'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function pdf(ConsultarAuxiliarRequest $peticion): Response
    {
        $cuentaId = $peticion->validated('cuenta_id');
        $terceroId = $peticion->validated('tercero_id');
        $alcance = $terceroId
            ? 'Movimientos del tercero'.($cuentaId ? ' en la cuenta seleccionada' : ' en todas las cuentas')
            : 'Movimientos de la cuenta seleccionada';

        $pdf = $this->informesPdf->auxiliar(
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            $cuentaId,
            $terceroId,
            $alcance,
        );

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
