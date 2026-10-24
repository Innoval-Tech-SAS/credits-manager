<?php

namespace App\Conciliacion\Models;

use App\Conciliacion\Enums\TipoMarcaConciliacion;
use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\Comprobante;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`); además de este guardia,
 * la base rechaza UPDATE/DELETE con triggers. Desmarcar es una fila `reversa` que apunta a la marca original.
 * Se usa desde la Fase 6.2.
 */
#[Fillable(['movimiento_extracto_id', 'asiento_contable_id', 'comprobante_generado_id', 'tipo', 'marca_origen_id', 'valor', 'observacion', 'marcado_por'])]
class MarcaConciliacion extends Model
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $table = 'marcas_conciliacion';

    protected function casts(): array
    {
        return [
            'tipo' => TipoMarcaConciliacion::class,
            'valor' => DineroCast::class,
            'creado_en' => 'datetime',
        ];
    }

    public function movimientoExtracto(): BelongsTo
    {
        return $this->belongsTo(MovimientoExtracto::class, 'movimiento_extracto_id');
    }

    public function asientoContable(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_contable_id');
    }

    public function comprobanteGenerado(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_generado_id');
    }

    public function marcaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'marca_origen_id');
    }

    public function marcadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'marcado_por');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Las marcas de conciliación son de solo inserción: no admiten modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Las marcas de conciliación son de solo inserción: no admiten eliminación.');
    }
}
