<?php

namespace App\Facturacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['empresa_id', 'clave', 'valor', 'actualizado_por'])]
class ConfiguracionFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'facturacion_configuracion';
}
