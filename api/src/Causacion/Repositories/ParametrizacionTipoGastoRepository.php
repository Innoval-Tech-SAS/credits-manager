<?php

namespace App\Causacion\Repositories;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ParametrizacionTipoGastoRepository extends BaseRepository
{
    public function __construct(ParametrizacionTipoGasto $parametrizacion)
    {
        parent::__construct($parametrizacion);
    }

    public function porTipoGasto(int $tipoGastoId): Collection
    {
        return $this->consulta()->where('tipo_gasto_id', $tipoGastoId)->orderByDesc('fecha_desde')->get();
    }

    public function vigenteParaFecha(int $tipoGastoId, string $fecha): ?ParametrizacionTipoGasto
    {
        return $this->consulta()
            ->where('tipo_gasto_id', $tipoGastoId)
            ->where('fecha_desde', '<=', $fecha)
            ->where(fn ($query) => $query->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>=', $fecha))
            ->first();
    }

    /** La ficha vigente sin fecha de cierre para este tipo de gasto, si existe (la que hay que cerrar al crear una nueva versión). */
    public function abiertaParaTipoGasto(int $tipoGastoId): ?ParametrizacionTipoGasto
    {
        return $this->consulta()
            ->where('tipo_gasto_id', $tipoGastoId)
            ->whereNull('fecha_hasta')
            ->first();
    }

    public function existeEnRangoCerrado(int $tipoGastoId, string $fechaDesde): bool
    {
        return $this->consulta()
            ->where('tipo_gasto_id', $tipoGastoId)
            ->whereNotNull('fecha_hasta')
            ->where('fecha_desde', '<=', $fechaDesde)
            ->where('fecha_hasta', '>=', $fechaDesde)
            ->exists();
    }
}
