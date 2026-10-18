<?php

namespace App\Respaldos\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1, sembrada por la migración): nunca se crea ni se borra otra desde la aplicación,
 * solo se actualiza. No extiende `BaseModel`: no tiene soft delete, igual que `TareaProgramada`.
 */
#[Fillable(['activa', 'bucket', 'region', 'endpoint', 'uso_path_style', 'clave_acceso', 'actualizado_por'])]
#[Hidden(['clave_secreta'])]
class ConfiguracionRespaldoExterno extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'ultima_actualizacion';

    protected $table = 'configuraciones_respaldo_externo';

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'uso_path_style' => 'boolean',
            'clave_secreta' => 'encrypted',
        ];
    }
}
