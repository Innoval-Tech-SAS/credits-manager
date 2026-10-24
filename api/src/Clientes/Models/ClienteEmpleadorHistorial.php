<?php

namespace App\Clientes\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Empleadores\Models\Empleador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un período de empleo de un cliente en un empleador — `fecha_fin` nula significa que sigue vigente. */
#[Fillable(['cliente_id', 'empleador_id', 'fecha_inicio', 'fecha_fin'])]
class ClienteEmpleadorHistorial extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'cliente_empleador_historial';

    protected $appends = ['vigente'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleador(): BelongsTo
    {
        return $this->belongsTo(Empleador::class);
    }

    protected function vigente(): Attribute
    {
        return Attribute::make(get: fn () => is_null($this->fecha_fin));
    }
}
