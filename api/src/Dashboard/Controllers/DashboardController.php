<?php

namespace App\Dashboard\Controllers;

use App\Dashboard\Requests\ConsultarDashboardRequest;
use App\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    /** El asesor siempre queda limitado a sus clientes, aunque envíe otro `asesor_id`. */
    public function index(ConsultarDashboardRequest $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        return response()->json($this->dashboard->resumen(
            usuario: $usuario,
            empresaId: $peticion->empresaId(),
            asesorId: $usuario->idAsesorConAlcance() ?? $peticion->asesorId(),
            clienteId: $peticion->clienteId(),
            estado: $peticion->estado(),
            periodo: $peticion->periodo(),
        ));
    }
}
