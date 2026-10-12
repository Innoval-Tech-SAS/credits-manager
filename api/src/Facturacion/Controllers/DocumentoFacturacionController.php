<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Requests\ActualizarBorradorFacturaRequest;
use App\Facturacion\Requests\ConceptosSugeridosFacturaRequest;
use App\Facturacion\Requests\CrearBorradorFacturaRequest;
use App\Facturacion\Requests\EmitirNotaRequest;
use App\Facturacion\Requests\ListarDocumentosFacturacionRequest;
use App\Facturacion\Requests\MotivoAccionFacturacionRequest;
use App\Facturacion\Services\AccesoDocumentosFacturacion;
use App\Facturacion\Services\DocumentoFacturacionService;
use App\Facturacion\Services\EnvioFacturacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentoFacturacionController extends Controller
{
    public function __construct(
        protected DocumentoFacturacionService $documentos,
        protected EnvioFacturacionService $envios,
        protected AccesoDocumentosFacturacion $acceso,
    ) {}

    public function index(ListarDocumentosFacturacionRequest $peticion): JsonResponse
    {
        $documentos = $this->documentos->listar(
            $this->acceso->tiposVisibles($peticion->user(), $peticion->validated('tipo') ? TipoDocumentoFacturacion::from($peticion->validated('tipo')) : null),
            $peticion->validated('estado') ? EstadoDocumentoFacturacion::from($peticion->validated('estado')) : null,
            $peticion->integer('cliente_id') ?: null,
            $peticion->boolean('con_saldo'),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($documentos);
    }

    public function conceptosSugeridos(ConceptosSugeridosFacturaRequest $peticion): JsonResponse
    {
        return response()->json(['conceptos' => $this->documentos->conceptosSugeridos($peticion->integer('credito_id'))]);
    }

    public function store(CrearBorradorFacturaRequest $peticion): JsonResponse
    {
        $documento = $this->documentos->crearBorrador(
            $peticion->integer('credito_id'),
            $peticion->validated('fecha'),
            $peticion->validated('lineas'),
            $peticion->validated('fecha_vencimiento'),
            $peticion->user(),
        );

        return response()->json(['documento' => $documento], 201);
    }

    public function show(Request $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $this->acceso->exigirVer($peticion->user(), $documento);

        return response()->json($this->documentos->detalle($documento));
    }

    public function update(ActualizarBorradorFacturaRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $actualizado = $this->documentos->actualizarBorrador($documento, $peticion->validated('fecha'), $peticion->validated('lineas'), $peticion->validated('fecha_vencimiento'), $peticion->user());

        return response()->json($this->documentos->detalle($actualizado));
    }

    public function destroy(DocumentoFacturacion $documento): Response
    {
        $this->documentos->eliminarBorrador($documento);

        return response()->noContent();
    }

    public function emitir(Request $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $this->acceso->exigirVer($peticion->user(), $documento);

        $emitido = $this->documentos->emitirFactura($documento, $peticion->user());

        return response()->json($this->documentos->detalle($emitido));
    }

    public function nota(EmitirNotaRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $tipo = TipoDocumentoFacturacion::from($peticion->validated('tipo'));
        $accion = $tipo === TipoDocumentoFacturacion::NotaCredito ? 'anular_nota_credito' : 'anular_nota_debito';

        if (! $peticion->user()->tienePermiso('facturacion', $accion)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $nota = $this->documentos->emitirNota(
            $documento,
            $tipo,
            $peticion->validated('motivo_codigo'),
            $peticion->validated('motivo_detalle'),
            $peticion->validated('fecha_vencimiento'),
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
        $this->acceso->exigirVer($peticion->user(), $documento);

        $pdf = $this->documentos->generarPdf($documento, $peticion->user());

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }

    public function reenviar(Request $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $this->acceso->exigirVer($peticion->user(), $documento);

        $envio = $this->envios->solicitar($peticion->user(), $documento);

        return response()->json(['envio' => $this->envios->representar($envio)], 202);
    }

    public function historialEnvios(Request $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $this->acceso->exigirVer($peticion->user(), $documento);

        return response()->json($this->envios->historial($documento));
    }

    public function reintentarEnvio(Request $peticion, DocumentoFacturacion $documento, int $envio): JsonResponse
    {
        $this->acceso->exigirVer($peticion->user(), $documento);

        $reintentado = $this->envios->reintentar($peticion->user(), $documento, $envio);

        return response()->json(['envio' => $this->envios->representar($reintentado)], 202);
    }
}
