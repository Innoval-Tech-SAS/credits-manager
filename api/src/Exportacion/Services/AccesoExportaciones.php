<?php

namespace App\Exportacion\Services;

use App\Exportacion\Constants\ExportacionConstants;
use App\Usuarios\Models\Usuario;

/**
 * Regla de acceso a una exportación por la clave de su reporte, la misma al solicitarla (ruta y `autorizadoPara`) que
 * al descargarla: quien perdió el permiso después de pedir el archivo ya no lo descarga. El mapa se recibe por
 * constructor (por defecto, `ExportacionConstants::PERMISOS_POR_REPORTE`) para que las pruebas registren sus reportes.
 */
class AccesoExportaciones
{
    /** @param array<string, list<string>> $permisosPorReporte */
    public function __construct(protected array $permisosPorReporte = ExportacionConstants::PERMISOS_POR_REPORTE) {}

    public function puede(Usuario $usuario, string $reporte): bool
    {
        if ($usuario->es_superadmin && in_array($reporte, ExportacionConstants::REPORTES_DE_PLATAFORMA, true)) {
            return true;
        }

        foreach ($this->permisosPorReporte[$reporte] ?? [] as $clave) {
            [$modulo, $accion] = explode('.', $clave, 2);

            if ($usuario->tienePermiso($modulo, $accion)) {
                return true;
            }
        }

        return false;
    }

    public function exigir(Usuario $usuario, string $reporte): void
    {
        abort_unless($this->puede($usuario, $reporte), 403, 'No tienes permiso para realizar esta acción.');
    }
}
