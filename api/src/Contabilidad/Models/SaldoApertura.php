<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cuenta_id', 'tercero_id', 'fecha_corte', 'debito', 'credito'])]
class SaldoApertura extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'saldos_apertura';

    protected function casts(): array
    {
        return [
            'fecha_corte' => 'date:Y-m-d',
            'debito' => DineroCast::class,
            'credito' => DineroCast::class,
        ];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }
}
