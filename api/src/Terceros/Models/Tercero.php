<?php

namespace App\Terceros\Models;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Gastos\Models\ActividadEconomica;
use App\Proveedores\Models\Proveedor;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tipo_documento',
    'numero_documento',
    'nombre',
    'nombres',
    'apellidos',
    'razon_social',
    'digito_verificacion',
    'representante_legal',
    'direccion',
    'barrio',
    'ciudad',
    'celular',
    'telefono_fijo',
    'correo',
    'regimen_tributario',
    'tipo_persona',
    'autorretenedor',
    'responsable_iva',
    'actividad_economica_id',
    'activo',
])]
class Tercero extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'terceros';

    protected $appends = ['tipo'];

    /** Solo se cargan para calcular `tipo`; el objeto anidado completo no aporta nada a la API. */
    protected $hidden = ['cliente', 'proveedor', 'otroTercero'];

    /** `nombre` es siempre derivado (nombres y apellidos, o razón social): un único punto para que ningún camino de escritura lo desincronice. */
    protected static function booted(): void
    {
        static::saving(function (Tercero $tercero) {
            $nombre = match ($tercero->tipo_persona) {
                TipoPersona::Natural => filled($tercero->nombres) ? trim($tercero->nombres.' '.$tercero->apellidos) : null,
                TipoPersona::Juridica => $tercero->razon_social,
                default => null,
            };

            if (filled($nombre)) {
                $tercero->nombre = $nombre;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoTercero::class,
            'tipo_persona' => TipoPersona::class,
            'autorretenedor' => 'boolean',
            'responsable_iva' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** 'cliente'|'proveedor'|'otro'|null — a qué extensión pertenece este tercero, para mostrarlo en el listado. */
    protected function tipo(): Attribute
    {
        return Attribute::make(get: fn () => match (true) {
            $this->cliente !== null => 'cliente',
            $this->proveedor !== null => 'proveedor',
            $this->otroTercero !== null => 'otro',
            default => null,
        });
    }

    /** Número de documento tal como se imprime: el NIT lleva su dígito de verificación. */
    public function documentoConDigito(): string
    {
        return $this->tipo_persona === TipoPersona::Juridica && $this->digito_verificacion !== null
            ? "{$this->numero_documento}-{$this->digito_verificacion}"
            : $this->numero_documento;
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    public function proveedor(): HasOne
    {
        return $this->hasOne(Proveedor::class);
    }

    public function otroTercero(): HasOne
    {
        return $this->hasOne(OtroTercero::class);
    }

    public function actividadEconomica(): BelongsTo
    {
        return $this->belongsTo(ActividadEconomica::class);
    }
}
