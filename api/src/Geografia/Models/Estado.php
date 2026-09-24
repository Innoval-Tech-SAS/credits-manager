<?php

namespace App\Geografia\Models;

use App\Core\Base\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo global (sin `empresa_id`); en Colombia son los departamentos y Bogotá D.C. */
#[Fillable(['pais_id', 'codigo', 'nombre', 'activo'])]
class Estado extends BaseModel
{
    protected $table = 'estados';

    protected function casts(): array
    {
        return [
            'pais_id' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
