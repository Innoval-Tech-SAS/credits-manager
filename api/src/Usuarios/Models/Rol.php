<?php

namespace App\Usuarios\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'nombre', 'descripcion'])]
class Rol extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'roles';

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso')->using(RolPermiso::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
