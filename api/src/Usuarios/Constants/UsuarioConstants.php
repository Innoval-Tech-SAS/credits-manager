<?php

namespace App\Usuarios\Constants;

class UsuarioConstants
{
    public const ROL_ADMIN = 'admin';

    public const ROL_ASESOR = 'asesor';

    /** Roles a los que se puede asignar un cliente: el administrador recibe los que nadie más atiende. */
    public const ROLES_ASIGNABLES = [self::ROL_ASESOR, self::ROL_ADMIN];

    public const COLUMNAS_ORDENABLES = ['nombre', 'usuario', 'rol_id', 'activo', 'creado_en'];
}
