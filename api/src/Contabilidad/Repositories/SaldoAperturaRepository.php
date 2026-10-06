<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\SaldoApertura;
use App\Core\Base\BaseRepository;
use Illuminate\Support\Collection;

class SaldoAperturaRepository extends BaseRepository
{
    public function __construct(SaldoApertura $saldoApertura)
    {
        parent::__construct($saldoApertura);
    }

    /** Último saldo cargado de cada cuenta con fecha de corte hasta `$fecha`, indexado por el id de la cuenta. */
    public function vigentesHasta(int $empresaId, string $fecha): Collection
    {
        return $this->consulta()
            ->with('cuenta')
            ->where('empresa_id', $empresaId)
            ->where('fecha_corte', '<=', $fecha)
            ->orderByDesc('fecha_corte')
            ->get()
            ->unique('cuenta_id')
            ->keyBy('cuenta_id');
    }
}
