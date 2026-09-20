<?php

namespace App\Gastos\Models;

use App\Core\Base\BaseModel;
use App\Gastos\Enums\TipoRetencion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gasto_id', 'tipo_retencion', 'incluida', 'regla', 'tarifa_aplicada', 'detalle'])]
class GastoReglaFiscalAplicada extends BaseModel
{
    protected $table = 'gasto_reglas_fiscales_aplicadas';

    protected function casts(): array
    {
        return [
            'tipo_retencion' => TipoRetencion::class,
            'incluida' => 'boolean',
            'tarifa_aplicada' => 'decimal:3',
        ];
    }

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }
}
