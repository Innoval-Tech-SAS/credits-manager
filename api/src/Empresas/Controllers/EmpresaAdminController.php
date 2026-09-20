<?php

namespace App\Empresas\Controllers;

use App\Empresas\Models\Empresa;
use App\Empresas\Requests\CrearEmpresaRequest;
use App\Empresas\Requests\EditarEmpresaRequest;
use App\Empresas\Services\EmpresaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Panel de super-admin: alta y administración de todas las empresas de la plataforma. */
class EmpresaAdminController extends Controller
{
    public function __construct(protected EmpresaService $empresas) {}

    public function index(Request $peticion): JsonResponse
    {
        $empresas = $this->empresas->listarEmpresas(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($empresas);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        return response()->json(['empresa' => $empresa]);
    }

    public function store(CrearEmpresaRequest $peticion): JsonResponse
    {
        $empresa = $this->empresas->crearEmpresaConAdmin($peticion->validated());

        return response()->json(['empresa' => $empresa], 201);
    }

    public function update(EditarEmpresaRequest $peticion, Empresa $empresa): JsonResponse
    {
        $empresa = $this->empresas->editarEmpresa($empresa, $peticion->validated());

        return response()->json(['empresa' => $empresa]);
    }
}
