<?php

namespace App\Seguridad\Controllers;

use App\Http\Controllers\Controller;
use App\Seguridad\Repositories\AccesoRepository;
use App\Seguridad\Requests\ListarAccesosRequest;
use Illuminate\Http\JsonResponse;

/** Solo superadmin (middleware de ruta): historial de accesos entre todas las empresas, con alerta de fuerza bruta. */
class AccesoController extends Controller
{
    public function __construct(protected AccesoRepository $accesos) {}

    public function index(ListarAccesosRequest $peticion): JsonResponse
    {
        return response()->json($this->accesos->paginar(
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            $peticion->validated('empresa_id'),
            $peticion->integer('per_page', 15),
            $peticion->integer('page', 1),
        ));
    }

    /** Aparte del listado para que `usePaginatedResource` (que espera un paginador plano) no tenga que cargar con ella. */
    public function alerta(): JsonResponse
    {
        return response()->json(['alerta_fuerza_bruta' => $this->accesos->hayPatronDeFuerzaBruta()]);
    }
}
