<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\ReinicioConsecutivo;
use App\Contabilidad\Enums\TipoNumeracionComprobante;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo', 'nombre', 'activo', 'consecutivo_actual',
    'consecutivo_inicial', 'consecutivo_final', 'tipo_numeracion', 'reinicio_periodo', 'ciclo_numeracion_actual',
])]
class TipoComprobante extends BaseModel
{
    use PerteneceAEmpresa;

    /** A partir de cuántos números libres se marca `proximo_a_agotarse` (solo aplica si hay `consecutivo_final`). */
    private const UMBRAL_ALERTA_AGOTAMIENTO = 10;

    protected $table = 'tipos_comprobante';

    protected $appends = ['proximo_a_agotarse'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'consecutivo_actual' => 'integer',
            'consecutivo_inicial' => 'integer',
            'consecutivo_final' => 'integer',
            'tipo_numeracion' => TipoNumeracionComprobante::class,
            'reinicio_periodo' => ReinicioConsecutivo::class,
        ];
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    protected function proximoAAgotarse(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->consecutivo_final !== null
                && ($this->consecutivo_final - $this->consecutivo_actual) <= self::UMBRAL_ALERTA_AGOTAMIENTO,
        );
    }
}
