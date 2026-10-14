<?php

namespace App\Monitoreo\Controllers;

use App\Http\Controllers\Controller;
use App\Monitoreo\Requests\ActualizarTareaProgramadaRequest;
use App\Monitoreo\Requests\ListarHistorialTareaProgramadaRequest;
use App\Monitoreo\Services\TareaProgramadaService;
use Illuminate\Http\JsonResponse;

/** Administración de la hora/pausa de las tareas programadas: solo superadmin (middleware de ruta). */
class TareaProgramadaController extends Controller
{
    public function __construct(protected TareaProgramadaService $tareas) {}

    public function index(): JsonResponse
    {
        return response()->json(['tareas' => $this->tareas->listar()]);
    }

    public function update(ActualizarTareaProgramadaRequest $peticion, string $clave): JsonResponse
    {
        $tarea = $this->tareas->actualizar($clave, $peticion->string('hora')->toString(), $peticion->boolean('activa'));

        return response()->json(['tarea' => $tarea]);
    }

    public function historial(ListarHistorialTareaProgramadaRequest $peticion, string $clave): JsonResponse
    {
        return response()->json($this->tareas->historial($clave, $peticion->integer('per_page', 15), $peticion->integer('page', 1)));
    }
}
