<?php

namespace App\Pagos\Models;

use App\Contabilidad\Models\Comprobante;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`); además de este guardia, la base
 * rechaza UPDATE/DELETE con triggers. La reversa de un pago es una fila que lo apunta: el pago no cambia.
 */
#[Fillable(['pago_id', 'comprobante_reverso_id', 'fecha', 'valor', 'motivo', 'registrado_por'])]
class ReversaPago extends Model
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $table = 'reversas_pago';

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'valor' => DineroCast::class,
            'creado_en' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_reverso_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Las reversas de pagos son de solo inserción: no admiten modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Las reversas de pagos son de solo inserción: no admiten eliminación.');
    }
}
