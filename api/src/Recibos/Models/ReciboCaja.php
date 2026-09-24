<?php

namespace App\Recibos\Models;

use App\Contabilidad\Models\Comprobante;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Pagos\Models\Pago;
use App\Recibos\Enums\EstadoRecibo;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento numerado de un pago. El valor, el cliente y el medio de pago no se copian: salen del pago, que no se edita.
 * `numero` sí es una copia deliberada, asignada al emitir y nunca modificada.
 */
#[Fillable(['pago_id', 'comprobante_id', 'consecutivo', 'numero', 'estado', 'emitido_por'])]
class ReciboCaja extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'recibos_caja';

    protected function casts(): array
    {
        return [
            'estado' => EstadoRecibo::class,
            'consecutivo' => 'integer',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emitido_por');
    }
}
