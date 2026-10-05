<?php

namespace App\Facturacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un documento de facturación. La nota crédito enlaza la línea de la factura que corrige (`linea_origen_id`),
 * así el saldo por concepto se controla contra la línea original y no contra el total.
 */
#[Fillable(['documento_id', 'linea_origen_id', 'orden', 'concepto', 'descripcion', 'valor'])]
class LineaDocumentoFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'lineas_documento_facturacion';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'valor' => DineroCast::class,
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
}
