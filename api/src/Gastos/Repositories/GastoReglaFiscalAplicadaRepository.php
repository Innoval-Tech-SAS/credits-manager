<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\GastoReglaFiscalAplicada;

class GastoReglaFiscalAplicadaRepository extends BaseRepository
{
    public function __construct(GastoReglaFiscalAplicada $reglaAplicada)
    {
        parent::__construct($reglaAplicada);
    }

    public function crearVarias(int $gastoId, array $reglas): void
    {
        $ahora = now();

        GastoReglaFiscalAplicada::insert(array_map(fn (array $regla) => [
            'gasto_id' => $gastoId,
            'tipo_retencion' => $regla['tipo']->value,
            'incluida' => $regla['incluida'],
            'regla' => $regla['regla'],
            'tarifa_aplicada' => $regla['tarifa_aplicada'] ?? null,
            'detalle' => $regla['detalle'] ?? null,
            'creado_en' => $ahora,
            'ultima_actualizacion' => $ahora,
        ], $reglas));
    }
}
