<?php

namespace App\Usuarios\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RolPermiso extends Pivot
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'ultima_actualizacion';

    protected $table = 'rol_permiso';
}
