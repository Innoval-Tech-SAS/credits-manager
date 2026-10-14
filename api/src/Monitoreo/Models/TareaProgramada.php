<?php

namespace App\Monitoreo\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo fijo de tareas programadas (ver `TareaProgramadaConstants::CLAVES`): nunca se crea ni se
 * borra una fila desde la aplicación, solo se pausa (`activa=false`) o se cambia su `hora`. No extiende
 * `BaseModel`: no tiene soft delete ni tendría sentido tenerlo en un catálogo fijo.
 */
#[Fillable(['hora', 'activa'])]
class TareaProgramada extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'ultima_actualizacion';

    protected $table = 'tareas_programadas';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
