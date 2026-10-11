<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Requests\CrearCentroCostoRequest;
use App\Contabilidad\Requests\EditarCentroCostoRequest;
use App\Contabilidad\Services\CentroCostoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CentroCostoController extends Controller
{
    public function __construct(protected CentroCostoService $centros) {}

    public function index(Request $peticion): JsonResponse
    {
        $centros = $this->centros->paginar(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($centros);
    }

    public function show(CentroCosto $centro): JsonResponse
    {
        return response()->json(['centro' => [
            ...$centro->toArray(),
            'tiene_movimientos_asociados' => $this->centros->tieneMovimientosAsociados($centro),
        ]]);
    }

    public function store(CrearCentroCostoRequest $peticion): JsonResponse
    {
        $centro = $this->centros->crear($peticion->validated());

        return response()->json(['centro' => $centro], 201);
    }

    public function update(EditarCentroCostoRequest $peticion, CentroCosto $centro): JsonResponse
    {
        $centro = $this->centros->editar($centro, $peticion->validated());

        return response()->json(['centro' => $centro]);
    }

    public function destroy(CentroCosto $centro): Response
    {
        $this->centros->eliminar($centro);

        return response()->noContent();
    }
}
