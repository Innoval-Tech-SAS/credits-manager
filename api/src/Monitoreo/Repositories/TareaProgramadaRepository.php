<?php

namespace App\Monitoreo\Repositories;

use App\Core\Base\BaseRepository;
use App\Monitoreo\Models\TareaProgramada;
use Illuminate\Database\Eloquent\Collection;

class TareaProgramadaRepository extends BaseRepository
{
    public function __construct(TareaProgramada $modelo)
    {
        parent::__construct($modelo);
    }

    public function todas(): Collection
    {
        return $this->consulta()->orderBy('clave')->get();
    }

    public function buscarPorClaveOFallar(string $clave): TareaProgramada
    {
        return $this->consulta()->where('clave', $clave)->firstOrFail();
    }
}
