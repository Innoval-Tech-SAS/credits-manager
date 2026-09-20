<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nombre',
    'naturaleza',
    'nivel',
    'cuenta_padre_id',
    'tipo',
    'permite_movimiento',
    'activa',
])]
class PlanCuenta extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'plan_cuentas';

    protected function casts(): array
    {
        return [
            'naturaleza' => NaturalezaCuenta::class,
            'tipo' => TipoCuenta::class,
            'permite_movimiento' => 'boolean',
            'activa' => 'boolean',
            'nivel' => 'integer',
        ];
    }

    public function cuentaPadre(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_padre_id');
    }

    public function cuentasHijas(): HasMany
    {
        return $this->hasMany(PlanCuenta::class, 'cuenta_padre_id');
    }
}
