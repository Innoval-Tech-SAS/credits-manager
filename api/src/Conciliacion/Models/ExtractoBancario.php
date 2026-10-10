<?php

namespace App\Conciliacion\Models;

use App\Conciliacion\Enums\EstadoExtracto;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cuenta_bancaria_id', 'fecha_desde', 'fecha_hasta', 'saldo_anterior', 'total_abonos', 'total_cargos', 'saldo_actual',
    'archivo_ruta', 'estado', 'motivo_rechazo', 'cargado_por',
    'cerrado_por', 'cerrado_en', 'reabierto_por', 'reabierto_en', 'motivo_reapertura',
])]
class ExtractoBancario extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'extractos_bancarios';

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date:Y-m-d',
            'fecha_hasta' => 'date:Y-m-d',
            'saldo_anterior' => DineroCast::class,
            'total_abonos' => DineroCast::class,
            'total_cargos' => DineroCast::class,
            'saldo_actual' => DineroCast::class,
            'estado' => EstadoExtracto::class,
            'cerrado_en' => 'datetime',
            'reabierto_en' => 'datetime',
        ];
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoExtracto::class)->orderBy('orden');
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cargado_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }

    public function reabiertoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reabierto_por');
    }
}
