<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\EventoContable;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evento', 'cuenta_debito_id', 'cuenta_credito_id', 'tipo_comprobante_id', 'activo'])]
class ReglaContabilizacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'reglas_contabilizacion';

    protected function casts(): array
    {
        return [
            'evento' => EventoContable::class,
            'activo' => 'boolean',
        ];
    }

    public function cuentaDebito(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_debito_id');
    }

    public function cuentaCredito(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_credito_id');
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class);
    }
}
