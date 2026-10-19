<?php

namespace App\Creditos\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use App\Creditos\Enums\EstadoCuota;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'credito_id',
    'numero_cuota',
    'fecha_vencimiento',
    'valor',
    'valor_interes',
    'interes_devengado_en',
    'valor_otros_gastos',
    'valor_capital',
    'saldo_capital',
    'valor_pagado',
    'estado',
])]
class Cuota extends BaseModel
{
    protected $table = 'cuotas';

    /** Total exigible: capital + interés + otros gastos. Lo calcula el servidor para no sumar montos en el cliente. */
    protected $appends = ['valor_total_cuota'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCuota::class,
            'fecha_vencimiento' => 'date:Y-m-d',
            'interes_devengado_en' => 'datetime',
            'valor' => DineroCast::class,
            'valor_interes' => DineroCast::class,
            'valor_otros_gastos' => DineroCast::class,
            'valor_capital' => DineroCast::class,
            'saldo_capital' => DineroCast::class,
            'valor_pagado' => DineroCast::class,
        ];
    }

    protected function valorTotalCuota(): Attribute
    {
        return Attribute::make(get: fn () => $this->valor->sumar($this->valor_otros_gastos));
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }
}
