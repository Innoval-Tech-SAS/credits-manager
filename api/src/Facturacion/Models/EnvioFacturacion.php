<?php

namespace App\Facturacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Correo\Enums\EstadoEnvioCorreo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'documento_facturacion_id',
    'plantilla_correo_id',
    'enviado_por',
    'destinatario',
    'asunto',
    'cuerpo',
    'estado',
    'detalle_error',
    'enviado_en',
])]
class EnvioFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'envios_facturacion';

    protected function casts(): array
    {
        return [
            'estado' => EstadoEnvioCorreo::class,
            'enviado_en' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFacturacion::class, 'documento_facturacion_id');
    }
}
