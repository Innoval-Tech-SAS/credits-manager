<?php

namespace App\Geografia\Controllers;

use App\Geografia\Models\Municipio;
use App\Geografia\Requests\CrearMunicipioRequest;
use App\Geografia\Requests\EditarMunicipioRequest;
use App\Geografia\Requests\ListarMunicipiosPaginadosRequest;
use App\Geografia\Requests\ListarMunicipiosRequest;
use App\Geografia\Services\MunicipioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MunicipioController extends Controller
{
    public function __construct(protected MunicipioService $municipios) {}

    public function index(ListarMunicipiosRequest $peticion): JsonResponse
    {
        $municipios = $this->municipios->buscar(
            $peticion->filled('estado_id') ? $peticion->integer('estado_id') : null,
            $peticion->filled('buscar') ? $peticion->string('buscar')->toString() : null,
            ! $peticion->user()->es_superadmin,
        );

        return response()->json(['municipios' => $municipios]);
    }

    public function listado(ListarMunicipiosPaginadosRequest $peticion): JsonResponse
    {
        $municipios = $this->municipios->listarPaginados(
            $peticion->integer('estado_id'),
            ! $peticion->user()->es_superadmin,
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($municipios);
    }

    public function store(CrearMunicipioRequest $peticion): JsonResponse
    {
        return response()->json(['municipio' => $this->municipios->crear($peticion->validated())], 201);
    }

    public function update(EditarMunicipioRequest $peticion, Municipio $municipio): JsonResponse
    {
        return response()->json(['municipio' => $this->municipios->editar($municipio, $peticion->validated())]);
    }
}
