<?php

namespace App\Usuarios\Controllers;

use App\Http\Controllers\Controller;
use App\Usuarios\Models\Rol;
use App\Usuarios\Requests\CrearRolRequest;
use App\Usuarios\Requests\EditarRolRequest;
use App\Usuarios\Services\RolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RolController extends Controller
{
    public function __construct(protected RolService $roles) {}

    public function index(Request $peticion): JsonResponse
    {
        $roles = $this->roles->paginar(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($roles);
    }

    public function show(Rol $rol): JsonResponse
    {
        return response()->json(['rol' => $rol->load('permisos')]);
    }

    public function store(CrearRolRequest $peticion): JsonResponse
    {
        $rol = $this->roles->crear($peticion->validated());

        return response()->json(['rol' => $rol->load('permisos')], 201);
    }

    public function update(EditarRolRequest $peticion, Rol $rol): JsonResponse
    {
        $rol = $this->roles->editar($rol, $peticion->validated());

        return response()->json(['rol' => $rol->load('permisos')]);
    }

    public function destroy(Rol $rol): Response
    {
        $this->roles->eliminar($rol);

        return response()->noContent();
    }
}
