<?php

namespace App\Facturacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use App\Pagos\Models\PagoDetalle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trazabilidad entre una línea de interés ya facturado y el `pago_detalle` que lo cubrió (ver la migración). */
#[Fillable(['linea_documento_facturacion_id', 'pago_detalle_id', 'valor'])]
class AplicacionInteresFacturado extends BaseModel
{
    protected $table = 'aplicaciones_interes_facturado';

    protected function casts(): array
    {
        return [
            'valor' => DineroCast::class,
        ];
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(LineaDocumentoFacturacion::class, 'linea_documento_facturacion_id');
    }

    public function pagoDetalle(): BelongsTo
    {
        return $this->belongsTo(PagoDetalle::class);
    }
}
