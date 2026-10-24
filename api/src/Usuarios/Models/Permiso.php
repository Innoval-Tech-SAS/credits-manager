<?php

namespace App\Usuarios\Models;

use App\Core\Base\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['modulo', 'accion'])]
class Permiso extends BaseModel
{
    protected $table = 'permisos';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso')->using(RolPermiso::class);
    }
}
