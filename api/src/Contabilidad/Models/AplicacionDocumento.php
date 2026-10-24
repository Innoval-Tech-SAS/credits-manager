<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'documento_tercero_id',
    'modulo_aplicacion',
    'aplicacion_id',
    'valor_aplicado',
    'fecha',
])]
class AplicacionDocumento extends BaseModel
{
    protected $table = 'aplicaciones_documento';

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'valor_aplicado' => DineroCast::class,
        ];
    }

    public function documentoTercero(): BelongsTo
    {
        return $this->belongsTo(DocumentoTercero::class);
    }
}
