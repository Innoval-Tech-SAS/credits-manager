<?php

namespace App\ObligacionesTributarias\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\ObligacionesTributarias\Enums\EstadoObligacionTributaria;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tipo_obligacion_id', 'periodo', 'fecha_limite', 'responsable_id', 'estado', 'observaciones', 'dias_alerta_previa', 'cumplida_en', 'cumplida_por', 'creado_por'])]
class ObligacionTributaria extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'obligaciones_tributarias';

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'estado' => EstadoObligacionTributaria::class,
            'cumplida_en' => 'datetime',
        ];
    }

    public function tipoObligacion(): BelongsTo
    {
        return $this->belongsTo(TipoObligacionTributaria::class, 'tipo_obligacion_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function cumplidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cumplida_por');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaObligacionTributaria::class, 'obligacion_id');
    }
}
