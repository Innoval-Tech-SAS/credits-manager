<?php

namespace App\Gastos\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['municipio_id', 'actividad_economica_id', 'fecha_desde', 'fecha_hasta', 'tarifa'])]
class TarifaReteIcaMunicipio extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tarifas_reteica_municipio';

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date:Y-m-d',
            'fecha_hasta' => 'date:Y-m-d',
            'tarifa' => 'decimal:3',
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function actividadEconomica(): BelongsTo
    {
        return $this->belongsTo(ActividadEconomica::class);
    }
}
