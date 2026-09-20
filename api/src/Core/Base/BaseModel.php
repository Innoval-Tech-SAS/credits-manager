<?php

namespace App\Core\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'ultima_actualizacion';

    const DELETED_AT = 'borrado_en';
}
