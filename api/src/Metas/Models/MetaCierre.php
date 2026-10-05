<?php

namespace App\Metas\Models;

use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Metas\Enums\EstadoMeta;
use App\Metas\Enums\TipoMeta;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`); además de este guardia,
 * la base rechaza UPDATE/DELETE con triggers. `asesor_id` nulo es la meta de empresa; `usuario_id` nulo, un cierre
 * automático del sistema. Un recálculo inserta una fila nueva que apunta a la anterior en `cierre_anterior_id`.
 */
#[Fillable(['empresa_id', 'meta_id', 'periodo', 'tipo', 'asesor_id', 'usuario_id', 'cierre_anterior_id', 'objetivo', 'ejecutado', 'porcentaje', 'estado', 'motivo', 'cerrado_en'])]
class MetaCierre extends Model
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'cerrado_en';

    public const UPDATED_AT = null;

    protected $table = 'metas_cierres';

    protected function casts(): array
    {
        return [
            'tipo' => TipoMeta::class,
            'objetivo' => DineroCast::class,
            'ejecutado' => DineroCast::class,
            'porcentaje' => 'decimal:2',
            'estado' => EstadoMeta::class,
            'cerrado_en' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Los cierres de metas son de solo inserción: no admiten modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Los cierres de metas son de solo inserción: no admiten eliminación.');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
