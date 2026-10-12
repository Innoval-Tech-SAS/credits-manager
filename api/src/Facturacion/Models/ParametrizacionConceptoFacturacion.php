<?php

namespace App\Facturacion\Models;

use App\Contabilidad\Models\PlanCuenta;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'tipo_concepto',
    'tarifa_iva', 'cuenta_iva_id',
    'aplica_retefuente', 'base_minima_uvt', 'tarifa_retefuente', 'cuenta_retefuente_id',
    'aplica_reteica', 'cuenta_reteica_id',
    'aplica_reteiva', 'tarifa_reteiva', 'cuenta_reteiva_id',
    'actualizado_por',
])]
class ParametrizacionConceptoFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'parametrizaciones_concepto_facturacion';

    protected function casts(): array
    {
        return [
            'tipo_concepto' => TipoConceptoFacturacion::class,
            'tarifa_iva' => 'decimal:3',
            'aplica_retefuente' => 'boolean',
            'base_minima_uvt' => 'integer',
            'tarifa_retefuente' => 'decimal:3',
            'aplica_reteica' => 'boolean',
            'aplica_reteiva' => 'boolean',
            'tarifa_reteiva' => 'decimal:3',
        ];
    }

    public function cuentaIva(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_iva_id');
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

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }
}
