<?php

namespace App\Gastos\Controllers;

use App\Gastos\Models\ActividadEconomica;
use App\Gastos\Requests\CrearActividadEconomicaRequest;
use App\Gastos\Requests\EditarActividadEconomicaRequest;
use App\Gastos\Services\ActividadEconomicaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ActividadEconomicaController extends Controller
{
    public function __construct(protected ActividadEconomicaService $actividades) {}

    public function index(): JsonResponse
    {
        return response()->json(['actividades_economicas' => $this->actividades->listar()]);
    }

    public function store(CrearActividadEconomicaRequest $peticion): JsonResponse
    {
        return response()->json(['actividad_economica' => $this->actividades->crear($peticion->validated())], 201);
    }

    public function update(EditarActividadEconomicaRequest $peticion, ActividadEconomica $actividadEconomica): JsonResponse
    {
        return response()->json(['actividad_economica' => $this->actividades->editar($actividadEconomica, $peticion->validated())]);
    }
}
