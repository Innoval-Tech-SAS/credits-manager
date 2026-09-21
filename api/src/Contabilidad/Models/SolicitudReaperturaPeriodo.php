<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\EstadoSolicitudReapertura;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['periodo_id', 'solicitado_por', 'motivo', 'estado', 'resuelto_por', 'resuelto_en', 'motivo_resolucion'])]
class SolicitudReaperturaPeriodo extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'solicitudes_reapertura_periodo';

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudReapertura::class,
            'resuelto_en' => 'datetime',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por');
    }

    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resuelto_por');
    }
}
