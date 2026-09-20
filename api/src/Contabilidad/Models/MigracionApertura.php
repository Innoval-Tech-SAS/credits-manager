<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipo',
    'archivo_original',
    'registros_aceptados',
    'registros_rechazados',
    'detalle_errores',
    'usuario_id',
])]
class MigracionApertura extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'migraciones_apertura';

    protected function casts(): array
    {
        return [
            'detalle_errores' => 'array',
            'registros_aceptados' => 'integer',
            'registros_rechazados' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
