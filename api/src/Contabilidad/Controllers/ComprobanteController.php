<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Requests\CrearComprobanteRequest;
use App\Contabilidad\Requests\ReversarComprobanteRequest;
use App\Contabilidad\Services\ComprobanteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComprobanteController extends Controller
{
    public function __construct(protected ComprobanteService $comprobantes) {}

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
        );

        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)], 201);
    }

    public function show(Comprobante $comprobante): JsonResponse
    {
        return response()->json(['comprobante' => $this->comprobantes->mostrar($comprobante)]);
    }

    public function reversar(ReversarComprobanteRequest $peticion, Comprobante $comprobante): JsonResponse
    {
        $reverso = $this->comprobantes->reversarComprobante($comprobante, $peticion->validated()['motivo']);

        return response()->json(['comprobante' => $this->comprobantes->mostrar($reverso)], 201);
    }
}
