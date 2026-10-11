<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\TipoComprobante;
use App\Contabilidad\Requests\CrearTipoComprobanteRequest;
use App\Contabilidad\Requests\EditarTipoComprobanteRequest;
use App\Contabilidad\Services\TipoComprobanteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TipoComprobanteController extends Controller
{
    public function __construct(protected TipoComprobanteService $tipos) {}

    public function index(Request $peticion): JsonResponse
    {
        $tipos = $this->tipos->paginar(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($tipos);
    }

    public function show(TipoComprobante $tipoComprobante): JsonResponse
    {
        return response()->json(['tipo' => [
            ...$tipoComprobante->toArray(),
            'tiene_comprobantes_asociados' => $this->tipos->tieneComprobantesAsociados($tipoComprobante),
        ]]);
    }

    public function store(CrearTipoComprobanteRequest $peticion): JsonResponse
    {
        $tipo = $this->tipos->crear($peticion->validated());

        return response()->json(['tipo' => $tipo], 201);
    }

    public function update(EditarTipoComprobanteRequest $peticion, TipoComprobante $tipoComprobante): JsonResponse
    {
        $tipo = $this->tipos->editar($tipoComprobante, $peticion->validated());

        return response()->json(['tipo' => $tipo]);
    }

    public function destroy(TipoComprobante $tipoComprobante): Response
    {
        $this->tipos->eliminar($tipoComprobante);

        return response()->noContent();
    }
}
