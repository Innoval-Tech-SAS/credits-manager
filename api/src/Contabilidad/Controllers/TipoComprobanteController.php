<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\CrearTipoComprobanteRequest;
use App\Contabilidad\Services\TipoComprobanteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(CrearTipoComprobanteRequest $peticion): JsonResponse
    {
        $tipo = $this->tipos->crear($peticion->validated());

        return response()->json(['tipo' => $tipo], 201);
    }
}
