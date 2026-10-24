<?php

namespace App\Geografia\Models;

use App\Core\Base\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo global (sin `empresa_id`): lo comparten todas las empresas y solo lo administra el superadmin. */
#[Fillable(['codigo', 'nombre', 'activo'])]
class Pais extends BaseModel
{
    protected $table = 'paises';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function estados(): HasMany
    {
        return $this->hasMany(Estado::class);
    }
}
