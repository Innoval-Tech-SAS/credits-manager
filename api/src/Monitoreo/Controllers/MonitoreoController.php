<?php

namespace App\Monitoreo\Controllers;

use App\Http\Controllers\Controller;
use App\Monitoreo\Requests\ListarTrabajosFallidosRequest;
use App\Monitoreo\Services\EstadoRespaldosService;
use App\Monitoreo\Services\PanelMonitoreoService;
use App\Monitoreo\Services\TrabajosFallidosService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Panel de salud del sistema: solo superadmin (middleware de ruta). Lee infraestructura, nunca datos de negocio. */
class MonitoreoController extends Controller
{
    public function __construct(
        protected PanelMonitoreoService $panel,
        protected EstadoRespaldosService $respaldos,
        protected TrabajosFallidosService $trabajosFallidos,
    ) {}

    public function estado(): JsonResponse
    {
        return response()->json($this->panel->instantanea());
    }

    public function respaldos(): JsonResponse
    {
        return response()->json($this->respaldos->resumen(CarbonImmutable::now()));
    }

    public function trabajosFallidos(ListarTrabajosFallidosRequest $peticion): JsonResponse
    {
        return response()->json($this->trabajosFallidos->listar(
            $peticion->validated('cola'),
            $peticion->integer('per_page', 15),
            $peticion->integer('page', 1),
        ));
    }

    public function reintentar(Request $peticion, string $uuid): JsonResponse
    {
        $this->trabajosFallidos->reintentar($peticion->user(), $uuid);

        return response()->json(['message' => 'El trabajo volvió a su cola.']);
    }
}
