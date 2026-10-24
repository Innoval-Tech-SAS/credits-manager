<?php

namespace App\Causacion\Repositories;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Causacion\Models\TipoGasto;
use App\Core\Base\BaseRepository;
use App\Gastos\Models\Gasto;
use Illuminate\Database\Eloquent\Collection;

class TipoGastoRepository extends BaseRepository
{
    public function __construct(TipoGasto $tipoGasto)
    {
        parent::__construct($tipoGasto);
    }

    public function todos(): Collection
    {
        return $this->consulta()->orderBy('nombre')->get();
    }

    public function activo(int $id): ?TipoGasto
    {
        return $this->consulta()->where('id', $id)->where('activo', true)->first();
    }

    /** Regla de negocio "sin movimientos": el tipo ya tiene gastos causados. */
    public function tieneGastosAsociados(int $tipoGastoId): bool
    {
        return Gasto::where('tipo_gasto_id', $tipoGastoId)->exists();
    }

    /** Evita que el `DELETE` reviente por el FK `RESTRICT` de parametrizaciones que usan este tipo. */
    public function estaEnUsoPorConfiguracion(int $tipoGastoId): bool
    {
        return ParametrizacionTipoGasto::where('tipo_gasto_id', $tipoGastoId)->exists();
    }
}
