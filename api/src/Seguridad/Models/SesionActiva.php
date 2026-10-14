<?php

namespace App\Seguridad\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * La tabla `sessions` de Laravel: infraestructura, no dato de negocio. Sin `empresa_id` propio (una
 * sesión es de un usuario, que sí lo tiene), sin timestamps de `BaseModel` (la tabla no los trae) y
 * sin soft delete: cerrar una sesión es borrado físico, igual que ya hace el propio framework al expirar.
 */
class SesionActiva extends Model
{
    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
