<?php

namespace App\Geografia\Repositories;

use App\Core\Base\BaseRepository;
use App\Geografia\Models\Pais;
use Illuminate\Database\Eloquent\Collection;

class PaisRepository extends BaseRepository
{
    public function __construct(Pais $pais)
    {
        parent::__construct($pais);
    }

    public function listar(bool $soloActivos): Collection
    {
        return $this->consulta()
            ->when($soloActivos, fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'activo']);
    }
}
