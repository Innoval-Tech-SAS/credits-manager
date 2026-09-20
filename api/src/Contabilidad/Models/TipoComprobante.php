<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['codigo', 'nombre', 'consecutivo_actual'])]
class TipoComprobante extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tipos_comprobante';

    protected function casts(): array
    {
        return [
            'consecutivo_actual' => 'integer',
        ];
    }
}
