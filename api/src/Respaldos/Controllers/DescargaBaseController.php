<?php

namespace App\Respaldos\Controllers;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Http\Controllers\Controller;
use App\Respaldos\Services\DescargaBaseService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DescargaBaseController extends Controller
{
    public function __construct(
        protected DescargaBaseService $descargas,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** Solo superadmin (ruta con middleware `superadmin`). Toda la instancia. */
    public function base(): BinaryFileResponse
    {
        $ruta = $this->descargas->baseCompleta();

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            'respaldos',
            'Descarga de la base completa (todas las empresas, sin contraseñas)',
        );

        $respuesta = response()->download($ruta, 'base-completa-'.now()->format('Y-m-d_His').'.sql.gz')->deleteFileAfterSend(true);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }

    /** Solo administradores de empresa (ruta con middleware `admin`). Filas de su empresa. */
    public function empresa(Request $peticion): BinaryFileResponse
    {
        $empresaId = $peticion->user()->empresa_id;
        $ruta = $this->descargas->empresa($empresaId);

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            'respaldos',
            'Descarga de los datos de la empresa (sin contraseñas)',
            empresaId: $empresaId,
        );

        $respuesta = response()->download($ruta, "empresa-{$empresaId}-".now()->format('Y-m-d_His').'.sql.gz')->deleteFileAfterSend(true);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
