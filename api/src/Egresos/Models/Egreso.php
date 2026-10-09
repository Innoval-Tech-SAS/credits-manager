<?php

namespace App\Egresos\Models;

use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Egresos\Enums\MedioPagoEgreso;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago de uno o más documentos por pagar del mismo tercero. Inmutable: no se edita ni se anula (ver el docblock de la migración). */
#[Fillable(['tercero_id', 'fecha', 'consecutivo', 'numero', 'valor', 'medio_pago', 'cuenta_caja_id', 'concepto', 'comprobante_id', 'creado_por'])]
class Egreso extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'egresos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'consecutivo' => 'integer',
            'valor' => DineroCast::class,
            'medio_pago' => MedioPagoEgreso::class,
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function cuentaCaja(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_caja_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
