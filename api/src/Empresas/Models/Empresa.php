<?php

namespace App\Empresas\Models;

use App\Core\Base\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['nombre_empresa', 'nit', 'direccion', 'telefono', 'correo', 'logo_path'])]
class Empresa extends BaseModel
{
    protected $table = 'empresas';
}
