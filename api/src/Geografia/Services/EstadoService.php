<?php

namespace App\Geografia\Services;

use App\Geografia\Repositories\EstadoRepository;
use Illuminate\Database\Eloquent\Collection;

class EstadoService
{
    public function __construct(protected EstadoRepository $estados) {}

    public function listarPorPais(int $paisId, bool $soloActivos): Collection
    {
        return $this->estados->listarPorPais($paisId, $soloActivos);
    }
}
