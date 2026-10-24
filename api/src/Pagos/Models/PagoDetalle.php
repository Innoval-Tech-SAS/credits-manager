<?php

namespace App\Pagos\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use App\Creditos\Models\Cuota;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pago_id', 'cuota_id', 'valor_aplicado'])]
class PagoDetalle extends BaseModel
{
    protected $table = 'pago_detalle';

    protected function casts(): array
    {
        return [
            'valor_aplicado' => DineroCast::class,
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
