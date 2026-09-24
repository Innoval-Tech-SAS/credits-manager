<?php

namespace App\Geografia\Models;

use App\Core\Base\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catálogo global (sin `empresa_id`). Lo contable por empresa (p. ej. tarifas ReteICA) vive en las tablas de Gastos y solo lo referencia. */
#[Fillable(['estado_id', 'codigo', 'nombre', 'es_capital', 'activo'])]
class Municipio extends BaseModel
{
    protected $table = 'municipios';

    protected function casts(): array
    {
        return [
            'estado_id' => 'integer',
            'es_capital' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }
}
