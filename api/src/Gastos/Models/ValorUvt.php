<?php

namespace App\Gastos\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/** Sin `empresa_id` — la UVT es una constante nacional (DIAN), un solo valor vigente por año para todas las empresas. */
#[Fillable(['anio', 'valor'])]
class ValorUvt extends BaseModel
{
    protected $table = 'valores_uvt';

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'valor' => DineroCast::class,
        ];
    }
}
