<?php

namespace App\Contabilidad\Controllers;

use App\Causacion\Services\CausacionService;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Requests\CausarConConceptoRequest;
use App\Contabilidad\Requests\CrearComprobanteRequest;
use App\Contabilidad\Requests\ReversarComprobanteRequest;
use App\Contabilidad\Services\ComprobantePdfService;
use App\Contabilidad\Services\ComprobanteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ComprobanteController extends Controller
{
    public function __construct(
        protected ComprobanteService $comprobantes,
        protected ComprobantePdfService $comprobantesPdf,
        protected CausacionService $causacion,
    ) {}

    public function index(Request $peticion): JsonResponse
    {
        $comprobantes = $this->comprobantes->listar(
            $peticion->string('search')->toString(),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($comprobantes);
    }

    public function store(CrearComprobanteRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $comprobante = $this->comprobantes->crearComprobante(
            $datos['tipo_comprobante_id'],
            $datos['fecha'],
            $datos['lineas'],
            $datos['concepto'],
            'manual',
            null,
            $datos['documento_soporte'] ?? null,
            $datos['tercero_id'] ?? null,
            contabilizarInmediato: false,
            consecutivoManual: $datos['consecutivo'] ?? null,
        );

        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)], 201);
    }

    public function causarConcepto(CausarConConceptoRequest $peticion): JsonResponse
    {
        $comprobante = $this->causacion->causar($peticion->validated());

        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)], 201);
    }

    public function revisar(Comprobante $comprobante): JsonResponse
    {
        $comprobante = $this->comprobantes->revisar($comprobante);

        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)]);
    }

    public function contabilizar(Comprobante $comprobante): JsonResponse
    {
        $comprobante = $this->comprobantes->contabilizar($comprobante);

        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)]);
    }

    public function show(Comprobante $comprobante): JsonResponse
    {
        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)]);
    }

    public function pdf(Comprobante $comprobante): Response
    {
        $pdf = $this->comprobantesPdf->generarPdf($comprobante);

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }

    public function reversar(ReversarComprobanteRequest $peticion, Comprobante $comprobante): JsonResponse
    {
        $resultado = $this->comprobantes->reversarComprobante($comprobante, $peticion->validated()['motivo']);

        return response()->json(['comprobante' => $this->comprobantes->mostrar($resultado)], 201);
    }
}
