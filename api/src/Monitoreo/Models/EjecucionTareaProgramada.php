<?php

namespace App\Monitoreo\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Tabla de solo inserción: una fila por ejecución ya terminada, escrita una sola vez cuando el
 * resultado ya se conoce (`RegistroEjecucionTarea`), nunca al iniciar ni actualizada después. Además de
 * este guardia, la base rechaza UPDATE/DELETE con triggers.
 */
#[Fillable(['tarea_clave', 'iniciado_en', 'finalizado_en', 'resultado', 'error'])]
class EjecucionTareaProgramada extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'ejecuciones_tareas_programadas';

    protected function casts(): array
    {
        return [
            'iniciado_en' => 'datetime',
            'finalizado_en' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Las ejecuciones de tareas programadas son de solo inserción: no admiten modificaciones.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Las ejecuciones de tareas programadas son de solo inserción: no admiten eliminación.');
    }
}
