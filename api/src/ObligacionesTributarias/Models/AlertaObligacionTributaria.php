<?php

namespace App\ObligacionesTributarias\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Notificaciones\Models\Notificacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Se inserta en lote y sin duplicar (ver AlertaObligacionTributariaRepository); `generada_en` hace de creación. */
#[Fillable(['vista_en'])]
class AlertaObligacionTributaria extends BaseModel
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'generada_en';

    protected $table = 'alertas_obligaciones_tributarias';

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'generada_en' => 'datetime',
            'vista_en' => 'datetime',
        ];
    }

    public function obligacion(): BelongsTo
    {
        return $this->belongsTo(ObligacionTributaria::class, 'obligacion_id');
    }

    public function notificacion(): BelongsTo
    {
        return $this->belongsTo(Notificacion::class);
    }
}
