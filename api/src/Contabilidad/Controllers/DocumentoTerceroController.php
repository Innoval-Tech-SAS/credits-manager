<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Http\Controllers\Controller;
use App\Terceros\Models\Tercero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentoTerceroController extends Controller
{
    public function __construct(protected DocumentoTerceroService $documentoTerceroService) {}

    public function pendientes(Tercero $tercero, Request $peticion): JsonResponse
    {
        $naturaleza = NaturalezaDocumento::tryFrom($peticion->string('naturaleza')->toString());
        abort_if(! $naturaleza, 422, __('validation.custom.documento.naturaleza_invalida'));

        return response()->json([
            'documentos' => $this->documentoTerceroService->pendientesPorTercero($tercero, $naturaleza),
        ]);
    }
}
