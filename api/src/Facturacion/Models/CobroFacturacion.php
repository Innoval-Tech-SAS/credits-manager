<?php

namespace App\Facturacion\Models;

use App\Contabilidad\Models\Comprobante;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Facturacion\Enums\MedioCobroFacturacion;
use App\Facturacion\Enums\MovimientoCobro;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`); además de este guardia,
 * la base rechaza UPDATE/DELETE con triggers. La corrección de un cobro es una fila `reversa` que lo apunta.
 */
#[Fillable(['documento_id', 'tipo', 'cobro_origen_id', 'comprobante_id', 'fecha', 'valor', 'medio', 'referencia', 'observacion', 'motivo', 'registrado_por'])]
class CobroFacturacion extends Model
{
    use PerteneceAEmpresa;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $table = 'cobros_facturacion';

    protected function casts(): array
    {
        return [
            'tipo' => MovimientoCobro::class,
            'medio' => MedioCobroFacturacion::class,
            'fecha' => 'date:Y-m-d',
            'valor' => DineroCast::class,
            'creado_en' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFacturacion::class, 'documento_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Los cobros de facturación son de solo inserción: no admiten modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Los cobros de facturación son de solo inserción: no admiten eliminación.');
    }
}
