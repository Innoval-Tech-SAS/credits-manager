<?php

namespace App\Monitoreo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `failed_jobs` del framework: la escribe el worker de cola y la borra `queue:retry`. No extiende
 * `BaseModel` (no tiene `creado_en` ni `borrado_en`) ni lleva `empresa_id`: es infraestructura, no
 * dato de negocio, y solo el superadmin la consulta.
 */
class TrabajoFallido extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'failed_at' => 'immutable_datetime',
        ];
    }
}
