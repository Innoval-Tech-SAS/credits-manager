<?php

namespace App\Geografia\Repositories;

use App\Core\Base\BaseRepository;
use App\Geografia\Models\Estado;
use Illuminate\Database\Eloquent\Collection;

class EstadoRepository extends BaseRepository
{
    public function __construct(Estado $estado)
    {
        parent::__construct($estado);
    }

    public function listarPorPais(int $paisId, bool $soloActivos): Collection
    {
        return $this->consulta()
            ->where('pais_id', $paisId)
            ->when($soloActivos, fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('nombre')
            ->get(['id', 'pais_id', 'codigo', 'nombre', 'activo']);
    }
}
