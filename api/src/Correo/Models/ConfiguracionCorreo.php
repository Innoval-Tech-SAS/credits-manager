<?php

namespace App\Correo\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\DriverCorreo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable([
    'remitente',
    'nombre_remitente',
    'driver',
    'host',
    'puerto',
    'cifrado',
    'usuario',
    'plantilla_asunto',
    'plantilla_cuerpo',
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
            'driver' => DriverCorreo::class,
            'cifrado' => CifradoCorreo::class,
            'puerto' => 'integer',
            'clave' => 'encrypted',
        ];
    }
}
