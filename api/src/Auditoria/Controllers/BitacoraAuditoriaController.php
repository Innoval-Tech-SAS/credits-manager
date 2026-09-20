<?php

namespace App\Auditoria\Controllers;

use App\Auditoria\Requests\ExportarBitacoraRequest;
use App\Auditoria\Requests\ListarBitacoraRequest;
use App\Auditoria\Services\BitacoraAuditoriaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BitacoraAuditoriaController extends Controller
{
    public function __construct(protected BitacoraAuditoriaService $bitacora) {}

    public function index(ListarBitacoraRequest $peticion): JsonResponse
    {
        $eventos = $this->bitacora->listar(
            $peticion->user(),
            $peticion->validated(),
            $peticion->integer('per_page', 15),
            $peticion->integer('page', 1),
        );

        return response()->json($eventos);
    }

    public function exportar(ExportarBitacoraRequest $peticion): JsonResponse
    {
        $exportacion = $this->bitacora->solicitarExportacion($peticion->user(), $peticion->validated());

        return response()->json(['exportacion' => $exportacion], 202);
    }
}
