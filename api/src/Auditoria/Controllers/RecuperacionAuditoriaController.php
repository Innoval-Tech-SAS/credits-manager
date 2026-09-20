<?php

namespace App\Auditoria\Controllers;

use App\Auditoria\Requests\ListarRespaldoAuditoriaRequest;
use App\Auditoria\Requests\RecuperarEventosAuditoriaRequest;
use App\Auditoria\Services\RecuperacionAuditoriaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Panel de recuperación del log de respaldo de auditoría: solo superadmin (middleware de ruta). */
class RecuperacionAuditoriaController extends Controller
{
    public function __construct(protected RecuperacionAuditoriaService $recuperacion) {}

    public function index(ListarRespaldoAuditoriaRequest $peticion): JsonResponse
    {
        return response()->json($this->recuperacion->listar(
            $peticion->validated('estado'),
            $peticion->integer('per_page', 15),
            $peticion->integer('page', 1),
        ));
    }

    public function recuperar(RecuperarEventosAuditoriaRequest $peticion): JsonResponse
    {
        $resultado = $this->recuperacion->recuperar(
            $peticion->user(),
            $peticion->boolean('todos') ? null : ($peticion->validated('evento_ids') ?? []),
        );

        return response()->json($resultado);
    }
}
