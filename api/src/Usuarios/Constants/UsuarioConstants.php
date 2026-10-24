<?php

namespace App\Usuarios\Constants;

class UsuarioConstants
{
    public const ROL_ADMIN = 'admin';

    public const ROL_ASESOR = 'asesor';

    /** Único rol sin empresa: lo crea la migración `asignar_rol_superadmin_y_limpiar_alcance_global`. */
    public const ROL_SUPERADMIN = 'superadmin';

    /** Roles base de toda empresa: no se renombran ni se eliminan porque el código los reconoce por nombre. */
    public const ROLES_PROTEGIDOS = [self::ROL_ADMIN, self::ROL_ASESOR];

    /** Roles a los que se puede asignar un cliente: el administrador recibe los que nadie más atiende. */
    public const ROLES_ASIGNABLES = [self::ROL_ASESOR, self::ROL_ADMIN];

    public const COLUMNAS_ORDENABLES = ['nombre', 'usuario', 'rol_id', 'activo', 'creado_en'];
}
