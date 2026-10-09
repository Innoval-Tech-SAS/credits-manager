<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Requests\ActualizarBorradorFacturaRequest;
use App\Facturacion\Requests\CrearBorradorFacturaRequest;
use App\Facturacion\Requests\EmitirNotaRequest;
use App\Facturacion\Requests\ListarDocumentosFacturacionRequest;
use App\Facturacion\Requests\MotivoAccionFacturacionRequest;
use App\Facturacion\Services\DocumentoFacturacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentoFacturacionController extends Controller
{
    public function __construct(protected DocumentoFacturacionService $documentos) {}

    public function index(ListarDocumentosFacturacionRequest $peticion): JsonResponse
    {
        $documentos = $this->documentos->listar(
            $peticion->validated('tipo') ? TipoDocumentoFacturacion::from($peticion->validated('tipo')) : null,
            $peticion->validated('estado') ? EstadoDocumentoFacturacion::from($peticion->validated('estado')) : null,
            $peticion->integer('cliente_id') ?: null,
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($documentos);
    }

    public function store(CrearBorradorFacturaRequest $peticion): JsonResponse
    {
        $documento = $this->documentos->crearBorrador(
            $peticion->integer('credito_id'),
            $peticion->validated('fecha'),
            $peticion->validated('lineas'),
            $peticion->validated('fecha_vencimiento'),
        );

        return response()->json(['documento' => $documento], 201);
    }

    public function show(DocumentoFacturacion $documento): JsonResponse
    {
        return response()->json($this->documentos->detalle($documento));
    }

    public function update(ActualizarBorradorFacturaRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $actualizado = $this->documentos->actualizarBorrador($documento, $peticion->validated('fecha'), $peticion->validated('lineas'), $peticion->validated('fecha_vencimiento'));

        return response()->json($this->documentos->detalle($actualizado));
    }

    public function destroy(DocumentoFacturacion $documento): Response
    {
        $this->documentos->eliminarBorrador($documento);

        return response()->noContent();
    }

    public function emitir(Request $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $emitido = $this->documentos->emitirFactura($documento, $peticion->user());

        return response()->json($this->documentos->detalle($emitido));
    }

    public function nota(EmitirNotaRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $nota = $this->documentos->emitirNota(
            $documento,
            TipoDocumentoFacturacion::from($peticion->validated('tipo')),
            $peticion->validated('motivo'),
            $peticion->validated('lineas'),
            $peticion->user(),
        );

        return response()->json($this->documentos->detalle($nota), 201);
    }

    public function anular(MotivoAccionFacturacionRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $anulada = $this->documentos->anular($documento, $peticion->validated('motivo'), $peticion->user());

        return response()->json($this->documentos->detalle($anulada));
    }

    public function pdf(Request $peticion, DocumentoFacturacion $documento): Response
    {
        $pdf = $this->documentos->generarPdf($documento, $peticion->user());

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
