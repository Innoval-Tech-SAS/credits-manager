<?php

namespace App\Conciliacion\Models;

use App\Conciliacion\Enums\EstadoMovimientoExtracto;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['extracto_bancario_id', 'fecha', 'descripcion', 'sucursal', 'documento', 'valor', 'saldo', 'orden', 'estado'])]
class MovimientoExtracto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'movimientos_extracto';

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'valor' => DineroCast::class,
            'saldo' => DineroCast::class,
            'orden' => 'integer',
            'estado' => EstadoMovimientoExtracto::class,
        ];
    }

    public function extracto(): BelongsTo
    {
        return $this->belongsTo(ExtractoBancario::class, 'extracto_bancario_id');
    }
}
