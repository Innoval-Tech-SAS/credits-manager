<?php

namespace App\Correo\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\TipoCorreo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable([
    'tipo',
    'remitente',
    'nombre_remitente',
    'host',
    'puerto',
    'cifrado',
    'usuario',
    'actualizado_por',
])]
#[Hidden(['clave'])]
class ConfiguracionCorreo extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'configuraciones_correo';

    protected function casts(): array
    {
        return [
            'tipo' => TipoCorreo::class,
            'cifrado' => CifradoCorreo::class,
            'puerto' => 'integer',
            'clave' => 'encrypted',
        ];
    }
}
