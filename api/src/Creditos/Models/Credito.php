<?php

namespace App\Creditos\Models;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Concerns\RestringidoPorAsesor;
use App\Core\Dinero\Dinero;
use App\Core\Dinero\DineroCast;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cliente_id',
    'tipo_credito',
    'credito_origen_id',
    'valor_prestamo',
    'saldo_refinanciado',
    'tasa_interes',
    'plazo',
    'tipo_pago',
    'valor_cuota',
    'valor_total',
    'fecha_desembolso',
    'desembolsado_en',
    'estado',
    'creado_por',
])]
class Credito extends BaseModel
{
    use PerteneceAEmpresa;
    use RestringidoPorAsesor;

    protected $table = 'creditos';

    protected static function columnaDeClienteParaAlcance(): ?string
    {
        return 'cliente_id';
    }

    protected function casts(): array
    {
        return [
            'tipo_credito' => TipoCredito::class,
            'tipo_pago' => TipoPago::class,
            'estado' => EstadoCredito::class,
            'valor_prestamo' => DineroCast::class,
            'saldo_refinanciado' => DineroCast::class,
            'tasa_interes' => 'decimal:3',
            'valor_cuota' => DineroCast::class,
            'valor_total' => DineroCast::class,
            'fecha_desembolso' => 'date:Y-m-d',
            'desembolsado_en' => 'datetime',
        ];
    }

    /** Dinero nuevo que sale de caja al desembolsar: en una refinanciación, lo prestado menos el saldo que ya estaba en cartera. */
    public function valorDesembolsado(): Dinero
    {
        return $this->saldo_refinanciado === null ? $this->valor_prestamo : $this->valor_prestamo->restar($this->saldo_refinanciado);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class)->orderBy('numero_cuota');
    }

    public function credito_origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credito_origen_id');
    }

    /** La refinanciación ya desembolsada que reemplazó a este crédito; solo existe cuando está refinanciado. */
    public function refinanciado_por(): HasOne
    {
        return $this->hasOne(self::class, 'credito_origen_id')->whereIn('estado', [EstadoCredito::Desembolsado, EstadoCredito::Pagado]);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
