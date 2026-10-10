<?php

namespace App\Conciliacion\Models;

use App\Conciliacion\Enums\EstadoMovimientoExtracto;
use App\Conciliacion\Enums\TipoMarcaConciliacion;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** La marca `conciliado`/`puente` vigente (sin una `reversa` que la deshaga), si el movimiento ya se resolvió. */
    public function marcaVigente(): HasOne
    {
        return $this->hasOne(MarcaConciliacion::class, 'movimiento_extracto_id')
            ->whereIn('tipo', [TipoMarcaConciliacion::Conciliado->value, TipoMarcaConciliacion::Puente->value])
            ->whereNotExists(fn ($reversa) => $reversa
                ->selectRaw('1')
                ->from('marcas_conciliacion as r')
                ->whereColumn('r.marca_origen_id', 'marcas_conciliacion.id'))
            ->latest('id');
    }
}
