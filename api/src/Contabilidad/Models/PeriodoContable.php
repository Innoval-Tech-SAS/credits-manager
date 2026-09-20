<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anio', 'mes', 'estado', 'cerrado_por', 'cerrado_en', 'reabierto_motivo'])]
class PeriodoContable extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'periodos_contables';

    protected function casts(): array
    {
        return [
            'estado' => EstadoPeriodo::class,
            'anio' => 'integer',
            'mes' => 'integer',
            'cerrado_en' => 'datetime',
        ];
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }
}
