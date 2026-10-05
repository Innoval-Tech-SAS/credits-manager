<?php

namespace App\Correo\Models;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Correo\Enums\EstadoEnvioCorreo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'cuota_id',
    'enviado_por',
    'plantilla',
    'destinatario',
    'asunto',
    'cuerpo',
    'estado',
    'detalle_error',
    'enviado_en',
])]
class EnvioCorreo extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'envios_correo';

    protected function casts(): array
    {
        return [
            'estado' => EstadoEnvioCorreo::class,
            'enviado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
