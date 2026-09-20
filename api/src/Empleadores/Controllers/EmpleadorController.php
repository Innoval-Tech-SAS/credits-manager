<?php

namespace App\Empleadores\Controllers;

use App\Empleadores\Models\Empleador;
use App\Empleadores\Requests\CrearEmpleadorRequest;
use App\Empleadores\Requests\EditarEmpleadorRequest;
use App\Empleadores\Services\EmpleadorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmpleadorController extends Controller
{
    public function __construct(protected EmpleadorService $empleadores) {}

    public function index(Request $peticion): JsonResponse
    {
        $empleadores = $this->empleadores->listarEmpleadores(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($empleadores);
    }

    public function store(CrearEmpleadorRequest $peticion): JsonResponse
    {
        $empleador = $this->empleadores->crear($peticion->validated());

        return response()->json(['empleador' => $empleador], 201);
    }

    public function show(Empleador $empleador): JsonResponse
    {
        return response()->json(['empleador' => $empleador]);
    }

    public function update(EditarEmpleadorRequest $peticion, Empleador $empleador): JsonResponse
    {
        $empleador = $this->empleadores->editar($empleador, $peticion->validated());

        return response()->json(['empleador' => $empleador]);
    }

    public function destroy(Empleador $empleador): Response
    {
        $this->empleadores->eliminar($empleador);

        return response()->noContent();
    }
}
