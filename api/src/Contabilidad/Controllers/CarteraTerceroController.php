<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Requests\ConsultarCarteraTerceroRequest;
use App\Contabilidad\Requests\ConsultarEdadesCarteraRequest;
use App\Contabilidad\Services\CarteraTerceroService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CarteraTerceroController extends Controller
{
    public function __construct(protected CarteraTerceroService $cartera) {}

    public function index(ConsultarCarteraTerceroRequest $peticion): JsonResponse
    {
        $documentos = $this->cartera->listar([
            'naturaleza' => NaturalezaDocumento::from($peticion->validated('naturaleza')),
            'tercero_id' => $peticion->integer('tercero_id') ?: null,
            'desde' => $peticion->validated('desde'),
            'hasta' => $peticion->validated('hasta'),
            'estado' => $peticion->validated('estado'),
            'plan_cuenta_id' => $peticion->integer('plan_cuenta_id') ?: null,
            'fecha_corte' => $peticion->validated('fecha_corte'),
        ], $peticion->integer('per_page', 10), $peticion->integer('page', 1));

        return response()->json($documentos);
    }

    public function edades(ConsultarEdadesCarteraRequest $peticion): JsonResponse
    {
        $edades = $this->cartera->edades(
            NaturalezaDocumento::from($peticion->validated('naturaleza')),
            $peticion->integer('tercero_id') ?: null,
            $peticion->validated('fecha_corte'),
        );

        return response()->json(['edades' => $edades]);
    }
}
