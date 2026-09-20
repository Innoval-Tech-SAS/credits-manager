<?php

namespace App\Gastos\Controllers;

use App\Gastos\Models\Municipio;
use App\Gastos\Requests\CrearMunicipioRequest;
use App\Gastos\Requests\EditarMunicipioRequest;
use App\Gastos\Services\MunicipioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MunicipioController extends Controller
{
    public function __construct(protected MunicipioService $municipios) {}

    public function index(): JsonResponse
    {
        return response()->json(['municipios' => $this->municipios->listar()]);
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
