<?php

namespace App\Clientes\Models;

use App\Core\Concerns\PerteneceAEmpresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`); además de este guardia,
 * la base rechaza UPDATE/DELETE con triggers. `asesor_anterior_id` es nulo solo en la asignación inicial.
 */
#[Fillable(['cliente_id', 'asesor_anterior_id', 'asesor_id', 'asignado_por', 'motivo'])]
class ClienteAsesorHistorial extends Model
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $table = 'cliente_asesor_historial';

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('El historial de asesores es de solo inserción: no admite modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('El historial de asesores es de solo inserción: no admite eliminación.');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asesor_id');
    }

    public function asesorAnterior(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asesor_anterior_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_por');
    }
}
