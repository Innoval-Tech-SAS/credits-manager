<?php

namespace App\Seguridad\Controllers;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Empresas\Models\Empresa;
use App\Http\Controllers\Controller;
use App\Seguridad\Models\SesionActiva;
use App\Seguridad\Repositories\SesionActivaRepository;
use Illuminate\Http\JsonResponse;

/** Solo superadmin (middleware de ruta): sesiones activas de todas las empresas, infraestructura, no negocio. */
class SesionActivaController extends Controller
{
    public function __construct(
        protected SesionActivaRepository $sesiones,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['sesiones' => $this->sesiones->listar()]);
    }

    public function destroy(SesionActiva $sesion): JsonResponse
    {
        $usuarioId = $sesion->user_id;
        $this->sesiones->cerrar($sesion->id);

        $this->auditoria->registrar(AccionAuditoria::Logout, 'seguridad', "Sesión cerrada por el superadmin (usuario #{$usuarioId})");

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function destroyEmpresa(Empresa $empresa): JsonResponse
    {
        $cerradas = $this->sesiones->cerrarDeEmpresa($empresa->id);

        $this->auditoria->registrar(
            AccionAuditoria::Logout,
            'seguridad',
            "Se cerraron {$cerradas} sesión(es) de la empresa {$empresa->nombre_empresa}",
            empresaId: $empresa->id,
        );

        return response()->json(['message' => "Se cerraron {$cerradas} sesión(es)."]);
    }
}
