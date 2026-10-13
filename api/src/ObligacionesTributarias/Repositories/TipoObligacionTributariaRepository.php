<?php

namespace App\ObligacionesTributarias\Repositories;

use App\Core\Base\BaseRepository;
use App\ObligacionesTributarias\Models\TipoObligacionTributaria;
use Illuminate\Database\Eloquent\Collection;

class TipoObligacionTributariaRepository extends BaseRepository
{
    public function __construct(TipoObligacionTributaria $tipo)
    {
        parent::__construct($tipo);
    }

    public function deEmpresa(int $empresaId, bool $soloActivos = false): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->when($soloActivos, fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('nombre')
            ->get();
    }
}
