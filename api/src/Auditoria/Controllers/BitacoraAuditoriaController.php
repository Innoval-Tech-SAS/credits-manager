<?php

namespace App\Auditoria\Controllers;

use App\Auditoria\Repositories\UsuarioAuditoriaRepository;
use App\Auditoria\Requests\ExportarBitacoraRequest;
use App\Auditoria\Requests\ListarBitacoraRequest;
use App\Auditoria\Services\BitacoraAuditoriaService;
use App\Http\Controllers\Controller;
use App\Usuarios\Models\Usuario;
use Illuminate\Http\JsonResponse;

class BitacoraAuditoriaController extends Controller
{
    public function __construct(
        protected BitacoraAuditoriaService $bitacora,
        protected UsuarioAuditoriaRepository $usuariosAuditoria,
    ) {}

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

    /** Solo superadmin (ruta con middleware `superadmin`): catálogo de usuarios de todas las empresas para el filtro "Autor". */
    public function usuarios(): JsonResponse
    {
        $usuarios = $this->usuariosAuditoria->listarTodos()->map(fn (Usuario $usuario) => [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'usuario' => $usuario->usuario,
            'empresa' => $usuario->nombre_empresa ?? 'Super Admin',
        ]);

        return response()->json(['usuarios' => $usuarios]);
    }

    public function exportar(ExportarBitacoraRequest $peticion): JsonResponse
    {
        $exportacion = $this->bitacora->solicitarExportacion($peticion->user(), $peticion->validated());

        return response()->json(['exportacion' => $exportacion], 202);
    }
}
