<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\Municipio;
use Illuminate\Database\Eloquent\Collection;

class MunicipioRepository extends BaseRepository
{
    public function __construct(Municipio $municipio)
    {
        parent::__construct($municipio);
    }

    public function todos(): Collection
    {
        return $this->consulta()->orderBy('nombre')->get();
    }
}
