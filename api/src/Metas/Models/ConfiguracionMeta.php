<?php

namespace App\Metas\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['empresa_id', 'clave', 'valor', 'actualizado_por'])]
class ConfiguracionMeta extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'metas_configuracion';
}
