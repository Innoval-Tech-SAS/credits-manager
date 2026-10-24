<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Models\CobroFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Requests\MotivoAccionFacturacionRequest;
use App\Facturacion\Requests\RegistrarCobroFacturaRequest;
use App\Facturacion\Requests\RegistrarCobroMultipleFacturaRequest;
use App\Facturacion\Services\CobroFacturacionService;
use App\Facturacion\Services\DocumentoFacturacionService;
use App\Http\Controllers\Controller;
use App\Recibos\Services\ReciboCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CobroFacturacionController extends Controller
{
    public function __construct(
        protected CobroFacturacionService $cobros,
        protected DocumentoFacturacionService $documentos,
        protected ReciboCajaService $recibos,
    ) {}

    public function store(RegistrarCobroFacturaRequest $peticion, DocumentoFacturacion $documento): JsonResponse
    {
        $cobro = $this->cobros->registrar($documento, $peticion->validated(), $peticion->user());

        return response()->json([...$this->documentos->detalle($cobro->documento), 'cobro' => $cobro], 201);
    }

    public function storeMultiple(RegistrarCobroMultipleFacturaRequest $peticion): JsonResponse
    {
        $cobros = $this->cobros->registrarMultiple($peticion->validated(), $peticion->user());

        return response()->json(['cobros' => $cobros], 201);
    }

    public function reversar(MotivoAccionFacturacionRequest $peticion, CobroFacturacion $cobro): JsonResponse
    {
        $reversa = $this->cobros->reversar($cobro, $peticion->validated('motivo'), $peticion->user());

        return response()->json([...$this->documentos->detalle($reversa->documento), 'reversa' => $reversa]);
    }

    public function pdf(CobroFacturacion $cobro): Response
    {
        $pdf = $this->recibos->generarPdfParaCobro($cobro);

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
