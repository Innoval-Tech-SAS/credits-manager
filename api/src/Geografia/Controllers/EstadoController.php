<?php

namespace App\Geografia\Controllers;

use App\Geografia\Requests\ListarEstadosRequest;
use App\Geografia\Services\EstadoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EstadoController extends Controller
{
    public function __construct(protected EstadoService $estados) {}

    public function index(ListarEstadosRequest $peticion): JsonResponse
    {
        $estados = $this->estados->listarPorPais($peticion->integer('pais_id'), ! $peticion->user()->es_superadmin);

        return response()->json(['estados' => $estados]);
    }
}
