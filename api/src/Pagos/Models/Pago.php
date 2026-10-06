<?php

namespace App\Pagos\Models;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Concerns\RestringidoPorAsesor;
use App\Core\Dinero\DineroCast;
use App\Creditos\Models\Credito;
use App\Pagos\Enums\FormaPago;
use App\Recibos\Models\ReciboCaja;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'credito_id',
    'cliente_id',
    'valor_pago',
    'valor_capital',
    'valor_interes',
    'valor_otros_gastos',
    'fecha_pago',
    'forma_pago',
    'observacion',
    'registrado_por',
])]
class Pago extends BaseModel
{
    use PerteneceAEmpresa;
    use RestringidoPorAsesor;

    protected $table = 'pagos';

    protected static function columnaDeClienteParaAlcance(): ?string
    {
        return 'cliente_id';
    }

    protected function casts(): array
    {
        return [
            'forma_pago' => FormaPago::class,
            'valor_pago' => DineroCast::class,
            'valor_capital' => DineroCast::class,
            'valor_interes' => DineroCast::class,
            'valor_otros_gastos' => DineroCast::class,
            'fecha_pago' => 'datetime:Y-m-d',
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function recibo(): HasOne
    {
        return $this->hasOne(ReciboCaja::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class);
    }
}
