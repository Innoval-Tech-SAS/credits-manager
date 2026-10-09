<?php

namespace App\Clientes\Services;

use App\Clientes\Constants\ClienteConstants;
use App\Clientes\Repositories\ComportamientoMensualRepository;
use App\Core\Dinero\Dinero;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ComportamientoMensualService
{
    public function __construct(protected ComportamientoMensualRepository $comportamiento) {}

    /**
     * Últimos doce meses, el actual incluido. El saldo de un mes es su saldo al cierre; en el mes actual, el de hoy.
     * Un mes sin movimientos repite el saldo del mes anterior.
     */
    public function serie(int $clienteId): array
    {
        $mesActual = CarbonImmutable::now('America/Bogota')->startOfMonth();
        $meses = array_map(
            fn (int $atras) => $mesActual->subMonths($atras)->format('Y-m'),
            range(ClienteConstants::MESES_COMPORTAMIENTO - 1, 0),
        );

        $recaudos = $this->comportamiento->recaudoPorMes($clienteId)->keyBy('mes');
        $cuotas = $this->comportamiento->cuotasPagadasPorMes($clienteId)->keyBy('mes');
        $saldos = $this->comportamiento->saldoAcumuladoPorMes($clienteId);

        $filas = array_map(fn (string $mes) => [
            'mes' => $mes,
            'recaudo' => Dinero::desdeCentavos((int) ($recaudos->get($mes)?->recaudado ?? 0)),
            'saldo_cierre' => Dinero::desdeCentavos($this->saldoAl($saldos, $mes)),
            'cuotas_a_tiempo' => (int) ($cuotas->get($mes)?->a_tiempo ?? 0),
            'cuotas_con_mora' => (int) ($cuotas->get($mes)?->con_mora ?? 0),
        ], $meses);

        return [
            'meses' => $filas,
            'totales' => [
                'recaudo' => Dinero::sumarVarios(...array_column($filas, 'recaudo')),
                'cuotas_a_tiempo' => array_sum(array_column($filas, 'cuotas_a_tiempo')),
                'cuotas_con_mora' => array_sum(array_column($filas, 'cuotas_con_mora')),
            ],
        ];
    }

    private function saldoAl(Collection $saldos, string $mes): int
    {
        return (int) ($saldos->filter(fn ($fila) => $fila->mes <= $mes)->last()?->saldo ?? 0);
    }
}
