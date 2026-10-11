<?php

namespace App\Correo\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Correo\Enums\TipoCorreo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['tipo', 'nombre', 'asunto', 'cuerpo', 'es_predeterminada', 'actualizado_por'])]
#[Hidden(['clave_predeterminada'])]
class PlantillaCorreo extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'plantillas_correo';

    protected function casts(): array
    {
        return [
            'tipo' => TipoCorreo::class,
            'es_predeterminada' => 'boolean',
        ];
    }
}
