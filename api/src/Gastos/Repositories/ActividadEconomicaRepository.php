<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\ActividadEconomica;
use Illuminate\Database\Eloquent\Collection;

class ActividadEconomicaRepository extends BaseRepository
{
    public function __construct(ActividadEconomica $actividadEconomica)
    {
        parent::__construct($actividadEconomica);
    }

    public function todos(): Collection
    {
        return $this->consulta()->orderBy('nombre')->get();
    }
}
