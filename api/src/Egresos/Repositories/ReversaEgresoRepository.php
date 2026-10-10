<?php

namespace App\Egresos\Repositories;

use App\Core\Base\BaseRepository;
use App\Egresos\Models\ReversaEgreso;

class ReversaEgresoRepository extends BaseRepository
{
    public function __construct(ReversaEgreso $reversa)
    {
        parent::__construct($reversa);
    }

    public function existePara(int $egresoId): bool
    {
        return $this->consulta()->where('egreso_id', $egresoId)->exists();
    }
}
