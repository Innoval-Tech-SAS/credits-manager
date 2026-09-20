<?php

namespace App\Usuarios\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UsuarioPermiso extends Pivot
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'ultima_actualizacion';

    protected $table = 'usuario_permiso';

    protected function casts(): array
    {
        return [
            'concedido' => 'boolean',
        ];
    }
}
