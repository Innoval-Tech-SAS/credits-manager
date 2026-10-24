<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Models\SolicitudReaperturaPeriodo;
use App\Contabilidad\Requests\RechazarSolicitudReaperturaRequest;
use App\Contabilidad\Requests\SolicitarReaperturaPeriodoRequest;
use App\Contabilidad\Services\SolicitudReaperturaPeriodoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudReaperturaPeriodoController extends Controller
{
    public function __construct(protected SolicitudReaperturaPeriodoService $solicitudes) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json(['solicitudes' => $this->solicitudes->listar($peticion->user())]);
    }

    public function store(SolicitarReaperturaPeriodoRequest $peticion, PeriodoContable $periodo): JsonResponse
    {
        $solicitud = $this->solicitudes->solicitar($periodo, $peticion->validated()['motivo'], $peticion->user());

        return response()->json(['solicitud' => $solicitud], 201);
    }

    public function autorizar(Request $peticion, SolicitudReaperturaPeriodo $solicitud): JsonResponse
    {
        return response()->json(['solicitud' => $this->solicitudes->autorizar($solicitud, $peticion->user())]);
    }

    public function rechazar(RechazarSolicitudReaperturaRequest $peticion, SolicitudReaperturaPeriodo $solicitud): JsonResponse
    {
        $solicitud = $this->solicitudes->rechazar($solicitud, $peticion->validated()['motivo_resolucion'], $peticion->user());

        return response()->json(['solicitud' => $solicitud]);
    }
}
