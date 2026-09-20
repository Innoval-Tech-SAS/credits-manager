<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\TipoGasto;
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
}
