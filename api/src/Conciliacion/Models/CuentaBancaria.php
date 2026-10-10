<?php

namespace App\Conciliacion\Models;

use App\Conciliacion\Enums\Banco;
use App\Conciliacion\Enums\TipoCuentaBancaria;
use App\Contabilidad\Models\PlanCuenta;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['banco', 'numero_cuenta', 'tipo_cuenta', 'alias', 'plan_cuenta_id', 'plan_cuenta_puente_id', 'activa'])]
class CuentaBancaria extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'cuentas_bancarias';

    protected function casts(): array
    {
        return [
            'banco' => Banco::class,
            'tipo_cuenta' => TipoCuentaBancaria::class,
            'activa' => 'boolean',
        ];
    }

    public function planCuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'plan_cuenta_id');
    }

    public function planCuentaPuente(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'plan_cuenta_puente_id');
    }

    public function extractos(): HasMany
    {
        return $this->hasMany(ExtractoBancario::class);
    }
}
