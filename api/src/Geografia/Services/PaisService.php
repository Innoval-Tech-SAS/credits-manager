<?php

namespace App\Geografia\Services;

use App\Geografia\Repositories\PaisRepository;
use Illuminate\Database\Eloquent\Collection;

class PaisService
{
    public function __construct(protected PaisRepository $paises) {}

    public function listar(bool $soloActivos): Collection
    {
        return $this->paises->listar($soloActivos);
    }
}
