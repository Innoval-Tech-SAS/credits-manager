<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoSolicitudReapertura;
use App\Contabilidad\Models\SolicitudReaperturaPeriodo;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class SolicitudReaperturaPeriodoRepository extends BaseRepository
{
    public function __construct(SolicitudReaperturaPeriodo $solicitud)
    {
        parent::__construct($solicitud);
    }

    /** Las pendientes primero y luego las más recientes; con `$solicitanteId` solo las de ese usuario. */
    public function listar(?int $solicitanteId = null): Collection
    {
        return $this->consulta()
            ->with(['periodo:id,anio,mes,estado', 'solicitante:id,nombre', 'resolutor:id,nombre'])
            ->when($solicitanteId !== null, fn ($consulta) => $consulta->where('solicitado_por', $solicitanteId))
            ->orderByRaw('case when estado = ? then 0 else 1 end', [EstadoSolicitudReapertura::Pendiente->value])
            ->orderByDesc('creado_en')
            ->limit(100)
            ->get();
    }

    public function existePendienteDelPeriodo(int $periodoId): bool
    {
        return $this->consulta()
            ->where('periodo_id', $periodoId)
            ->where('estado', EstadoSolicitudReapertura::Pendiente)
            ->exists();
    }

    /** Bloquea la fila: dos administradores no pueden resolver la misma solicitud a la vez. */
    public function bloquear(int $id): SolicitudReaperturaPeriodo
    {
        return $this->consulta()->lockForUpdate()->findOrFail($id);
    }
}
