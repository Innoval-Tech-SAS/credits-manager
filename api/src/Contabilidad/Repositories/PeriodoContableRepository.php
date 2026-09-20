<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\PeriodoContable;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class PeriodoContableRepository extends BaseRepository
{
    public function __construct(PeriodoContable $periodoContable)
    {
        parent::__construct($periodoContable);
    }

    public function obtenerOCrear(int $anio, int $mes): PeriodoContable
    {
        return $this->consulta()->firstOrCreate(
            ['anio' => $anio, 'mes' => $mes],
            ['estado' => EstadoPeriodo::Abierto],
        );
    }

    public function todosOrdenados(): Collection
    {
        return $this->consulta()->orderByDesc('anio')->orderByDesc('mes')->get();
    }
}
