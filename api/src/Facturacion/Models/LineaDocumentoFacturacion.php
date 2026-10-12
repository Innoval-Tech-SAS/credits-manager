<?php

namespace App\Facturacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Creditos\Models\Cuota;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un documento de facturación. La nota crédito enlaza la línea de la factura que corrige (`linea_origen_id`),
 * así el saldo por concepto se controla contra la línea original y no contra el total. `cuota_id`/`tipo_concepto`
 * solo se llenan en factura (trazan la línea a su cuota del plan de pagos); `valor_iva`/`valor_rete*` quedan
 * congelados al guardar — un documento emitido no se recalcula si cambia la parametrización después.
 */
#[Fillable([
    'documento_id', 'linea_origen_id', 'cuota_id', 'tipo_concepto', 'orden', 'concepto', 'descripcion', 'valor',
    'valor_iva', 'valor_retefuente', 'valor_reteica', 'valor_reteiva', 'tasa_mora', 'dias_mora', 'base_mora',
])]
class LineaDocumentoFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'lineas_documento_facturacion';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'tipo_concepto' => TipoConceptoFacturacion::class,
            'valor' => DineroCast::class,
            'valor_iva' => DineroCast::class,
            'valor_retefuente' => DineroCast::class,
            'valor_reteica' => DineroCast::class,
            'valor_reteiva' => DineroCast::class,
            'tasa_mora' => 'decimal:4',
            'dias_mora' => 'integer',
            'base_mora' => DineroCast::class,
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFacturacion::class, 'documento_id');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linea_origen_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
