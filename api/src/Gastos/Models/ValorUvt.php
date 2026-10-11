<?php

namespace App\Gastos\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/** La UVT la fija la DIAN cada año, pero cada empresa la administra por su cuenta (un admin no puede alterar la de otra). */
#[Fillable(['empresa_id', 'anio', 'valor'])]
class ValorUvt extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'valores_uvt';

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'valor' => DineroCast::class,
        ];
    }
}
