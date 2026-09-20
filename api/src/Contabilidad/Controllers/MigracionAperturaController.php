<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\ImportarMigracionAperturaRequest;
use App\Contabilidad\Services\MigracionAperturaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigracionAperturaController extends Controller
{
    public function __construct(protected MigracionAperturaService $migraciones) {}

    public function index(Request $peticion): JsonResponse
    {
        $migraciones = $this->migraciones->paginar(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($migraciones);
    }

    public function store(ImportarMigracionAperturaRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $migracion = $this->migraciones->importar($datos['tipo'], $peticion->file('archivo'));

        return response()->json(['migracion' => $migracion], 201);
    }
}
