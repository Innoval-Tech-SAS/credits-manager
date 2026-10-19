<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Requests\ConsultarCorteContableRequest;
use App\Contabilidad\Requests\ConsultarPeriodoContableRequest;
use App\Contabilidad\Services\DashboardContableService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardContableController extends Controller
{
    public function __construct(protected DashboardContableService $dashboard) {}

    public function situacion(ConsultarCorteContableRequest $peticion): JsonResponse
    {
        return response()->json($this->dashboard->situacion((int) $peticion->user()->empresa_id, $peticion->validated('hasta')));
    }

    public function resultados(ConsultarPeriodoContableRequest $peticion): JsonResponse
    {
        return response()->json($this->dashboard->resultados(
            (int) $peticion->user()->empresa_id,
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
        ));
    }

    public function cierre(ConsultarCorteContableRequest $peticion): JsonResponse
    {
        return response()->json($this->dashboard->cierre((int) $peticion->user()->empresa_id, $peticion->validated('hasta')));
    }
}
