<?php

namespace App\Auditoria\Models;

use App\Auditoria\Enums\AccionAuditoria;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Tabla de solo inserción (sin BaseModel: sin soft delete ni `ultima_actualizacion`). No usa
 * PerteneceAEmpresa: `empresa_id` es nullable y la visibilidad la decide un único método del
 * repositorio (`consultaVisible`). Además de este guardia, la base rechaza UPDATE/DELETE con triggers.
 */
#[Fillable([
    'evento_id', 'empresa_id', 'usuario_id', 'autor_nombre', 'autor_usuario', 'autor_rol', 'es_superadmin',
    'modulo', 'accion', 'descripcion', 'motivo', 'entidad', 'entidad_id',
    'valores_anteriores', 'valores_nuevos', 'ip', 'fecha_hora',
])]
class BitacoraAuditoria extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $table = 'bitacora_auditoria';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('La bitácora de auditoría es de solo inserción: no admite modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('La bitácora de auditoría es de solo inserción: no admite eliminación.');
    }

    public function forceDelete(): never
    {
        $this->delete();
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s.vP');
    }

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'usuario_id' => 'integer',
            'entidad_id' => 'integer',
            'es_superadmin' => 'boolean',
            'accion' => AccionAuditoria::class,
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'fecha_hora' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }
}
