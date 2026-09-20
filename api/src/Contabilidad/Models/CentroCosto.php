<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['codigo', 'nombre', 'activo'])]
class CentroCosto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'centros_costo';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
