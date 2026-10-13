<?php

namespace App\ObligacionesTributarias\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\ObligacionesTributarias\Enums\PeriodicidadObligacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'periodicidad', 'dias_alerta_previa', 'activo', 'creado_por'])]
class TipoObligacionTributaria extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tipos_obligacion_tributaria';

    protected function casts(): array
    {
        return [
            'periodicidad' => PeriodicidadObligacion::class,
            'activo' => 'boolean',
        ];
    }

    public function obligaciones(): HasMany
    {
        return $this->hasMany(ObligacionTributaria::class, 'tipo_obligacion_id');
    }
}
