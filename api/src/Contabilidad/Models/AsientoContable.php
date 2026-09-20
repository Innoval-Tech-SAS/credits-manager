<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'comprobante_id',
    'cuenta_id',
    'tercero_id',
    'centro_costo_id',
    'debito',
    'credito',
    'descripcion',
    'orden',
])]
class AsientoContable extends BaseModel
{
    protected $table = 'asientos_contables';

    protected function casts(): array
    {
        return [
            'debito' => DineroCast::class,
            'credito' => DineroCast::class,
            'orden' => 'integer',
        ];
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }
}
