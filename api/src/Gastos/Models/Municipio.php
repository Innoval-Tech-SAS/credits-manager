<?php

namespace App\Gastos\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['codigo', 'nombre', 'activo'])]
class Municipio extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'municipios';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
