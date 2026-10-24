<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Usuarios\Models\Usuario;

/**
 * Regla de acceso a la bitácora: superadmin, administrador de empresa o permiso explícito `auditoria.<accion>`. El
 * acceso del admin y del superadmin NO depende de que el permiso esté asignado. Se vuelve a comprobar en el servicio
 * además del middleware de ruta. Exportarla sigue la regla común de exportaciones (`AccesoExportaciones`).
 */
class AccesoAuditoria
{
    public function puede(Usuario $usuario, string $accion): bool
    {
        return $usuario->es_superadmin || $usuario->tienePermiso(AuditoriaConstants::MODULO, $accion);
    }

    public function exigir(Usuario $usuario, string $accion): void
    {
        abort_unless($this->puede($usuario, $accion), 403, 'No tienes permiso para realizar esta acción.');
    }
}
