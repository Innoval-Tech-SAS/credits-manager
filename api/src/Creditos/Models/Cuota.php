<?php

namespace App\Creditos\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use App\Creditos\Enums\EstadoCuota;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'credito_id',
    'numero_cuota',
    'fecha_vencimiento',
    'valor',
    'valor_interes',
    'valor_capital',
    'saldo_capital',
    'valor_pagado',
    'estado',
])]
class Cuota extends BaseModel
{
    protected $table = 'cuotas';

    protected function casts(): array
    {
        return [
            'estado' => EstadoCuota::class,
            'fecha_vencimiento' => 'date:Y-m-d',
            'valor' => DineroCast::class,
            'valor_interes' => DineroCast::class,
            'valor_capital' => DineroCast::class,
            'saldo_capital' => DineroCast::class,
            'valor_pagado' => DineroCast::class,
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }
}
