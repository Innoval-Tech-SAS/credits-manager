<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Constants\ContabilidadConstants;
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
    'exige_tercero',
    'exige_documento_cruce',
    'exige_vencimiento',
    'exige_centro_costo',
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
            'exige_tercero' => 'boolean',
            'exige_documento_cruce' => 'boolean',
            'exige_vencimiento' => 'boolean',
            'exige_centro_costo' => 'boolean',
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

    /**
     * Cartera de terceros: cuentas marcadas `exige_tercero` + `exige_documento_cruce`, o cuyo código cae en los
     * prefijos de CxC/CxP del PUC aunque nadie haya marcado las flags (las cuentas nacen sin marcar). Evita que
     * una 1305/2205/2335 sin configurar se salte en silencio el motor de `DocumentoTercero`.
     */
    public function esCarteraDeTerceros(): bool
    {
        if ($this->exige_tercero && $this->exige_documento_cruce) {
            return true;
        }

        $prefijos = [...ContabilidadConstants::PREFIJOS_CUENTAS_POR_COBRAR, ...ContabilidadConstants::PREFIJOS_CUENTAS_POR_PAGAR];

        foreach ($prefijos as $prefijo) {
            if (str_starts_with($this->codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
