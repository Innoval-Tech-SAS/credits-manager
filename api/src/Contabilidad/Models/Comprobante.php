<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tipo_comprobante_id',
    'consecutivo',
    'periodo_id',
    'fecha',
    'tercero_id',
    'concepto',
    'modulo_origen',
    'origen_id',
    'documento_soporte',
    'estado',
    'total_debito',
    'total_credito',
    'creado_por',
    'contabilizado_por',
    'contabilizado_en',
    'anulado_por',
    'anulado_en',
    'comprobante_reverso_id',
])]
class Comprobante extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'comprobantes';

    protected function casts(): array
    {
        return [
            'estado' => EstadoComprobante::class,
            'fecha' => 'date:Y-m-d',
            'total_debito' => DineroCast::class,
            'total_credito' => DineroCast::class,
            'contabilizado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function comprobanteReverso(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_reverso_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(AsientoContable::class)->orderBy('orden');
    }
}
