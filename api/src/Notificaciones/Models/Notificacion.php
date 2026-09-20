<?php

namespace App\Notificaciones\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Notificaciones\Enums\TipoNotificacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['empresa_id', 'usuario_id', 'tipo', 'titulo', 'mensaje', 'datos', 'leida_en'])]
class Notificacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'notificaciones';

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'usuario_id' => 'integer',
            'tipo' => TipoNotificacion::class,
            'datos' => 'array',
            'leida_en' => 'datetime',
        ];
    }
}
