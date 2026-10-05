<?php

namespace App\Metas\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Metas\Enums\EstadoMeta;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Las alertas se insertan en lote y sin duplicar (ver AlertaMetaRepository); `generada_en` hace de creación. */
#[Fillable(['vista_en'])]
class AlertaMeta extends BaseModel
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'generada_en';

    protected $table = 'alertas_metas';

    protected function casts(): array
    {
        return [
            'estado' => EstadoMeta::class,
            'generada_en' => 'datetime',
            'vista_en' => 'datetime',
        ];
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(Meta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
