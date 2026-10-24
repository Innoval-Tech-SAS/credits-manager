<?php

namespace App\Causacion\Models;

use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\TipoComprobante;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipo_gasto_id',
    'fecha_desde',
    'fecha_hasta',
    'tipo_comprobante_id',
    'cuenta_gasto_id',
    'cuenta_iva_id',
    'tarifa_iva',
    'cuenta_cxp_id',
    'aplica_retefuente',
    'concepto_dian',
    'base_minima_uvt',
    'tarifa_retefuente_persona_natural',
    'tarifa_retefuente_persona_juridica',
    'cuenta_retefuente_id',
    'aplica_reteica',
    'cuenta_reteica_id',
    'aplica_reteiva',
    'tarifa_reteiva',
    'cuenta_reteiva_id',
])]
class ParametrizacionTipoGasto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'parametrizaciones_tipo_gasto';

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date:Y-m-d',
            'fecha_hasta' => 'date:Y-m-d',
            'tarifa_iva' => 'decimal:3',
            'aplica_retefuente' => 'boolean',
            'base_minima_uvt' => 'integer',
            'tarifa_retefuente_persona_natural' => 'decimal:3',
            'tarifa_retefuente_persona_juridica' => 'decimal:3',
            'aplica_reteica' => 'boolean',
            'aplica_reteiva' => 'boolean',
            'tarifa_reteiva' => 'decimal:3',
        ];
    }

    public function tipoGasto(): BelongsTo
    {
        return $this->belongsTo(TipoGasto::class);
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function cuentaGasto(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_gasto_id');
    }

    public function cuentaIva(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_iva_id');
    }

    public function cuentaCxp(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_cxp_id');
    }

    public function cuentaRetefuente(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_retefuente_id');
    }

    public function cuentaReteica(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_reteica_id');
    }

    public function cuentaReteiva(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_reteiva_id');
    }
}
