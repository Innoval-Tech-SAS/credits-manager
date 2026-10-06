<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Contabilidad\Repositories\SaldoAperturaRepository;
use App\Core\Dinero\Dinero;

/**
 * Compara los saldos de apertura cargados por la importación contra el balance de prueba a la misma fecha, cuenta por
 * cuenta. Antes de producción ambos deben coincidir: una diferencia indica un saldo sin contabilizar o un movimiento
 * que se registró sobre la cuenta equivocada. La diferencia se expresa en saldo natural de la cuenta.
 */
class ConciliacionAperturaService
{
    public function __construct(
        protected SaldoAperturaRepository $saldos,
        protected PlanCuentaRepository $cuentas,
    ) {}

    public function conciliar(int $empresaId, string $hasta): array
    {
        $cargados = $this->saldos->vigentesHasta($empresaId, $hasta)->keyBy(fn ($saldo) => $saldo->cuenta->codigo);
        $balance = $this->cuentas->saldosParaBalancePrueba($empresaId, $hasta)->keyBy('codigo');

        $filas = $cargados->keys()
            ->merge($balance->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $codigo) use ($cargados, $balance) {
                $saldo = $cargados->get($codigo);
                $fila = $balance->get($codigo);

                $naturaleza = $saldo?->cuenta->naturaleza ?? $fila->naturaleza;
                $debitoCargado = $saldo?->debito ?? Dinero::cero();
                $creditoCargado = $saldo?->credito ?? Dinero::cero();
                $debitoBalance = Dinero::desdeCentavos((int) ($fila->debitos ?? 0));
                $creditoBalance = Dinero::desdeCentavos((int) ($fila->creditos ?? 0));

                $saldoCargado = $this->saldoNatural($naturaleza, $debitoCargado, $creditoCargado);
                $saldoBalance = $this->saldoNatural($naturaleza, $debitoBalance, $creditoBalance);

                return [
                    'codigo' => $codigo,
                    'nombre' => $saldo?->cuenta->nombre ?? $fila->nombre,
                    'cargado_debito' => $debitoCargado,
                    'cargado_credito' => $creditoCargado,
                    'balance_debito' => $debitoBalance,
                    'balance_credito' => $creditoBalance,
                    'diferencia' => $saldoCargado->restar($saldoBalance),
                ];
            });

        $diferencias = $filas->filter(fn (array $fila) => ! $fila['diferencia']->esCero());

        return [
            'filas' => $filas->all(),
            'total_cargado_debito' => Dinero::sumarVarios(...$filas->pluck('cargado_debito')->all()),
            'total_cargado_credito' => Dinero::sumarVarios(...$filas->pluck('cargado_credito')->all()),
            'total_balance_debito' => Dinero::sumarVarios(...$filas->pluck('balance_debito')->all()),
            'total_balance_credito' => Dinero::sumarVarios(...$filas->pluck('balance_credito')->all()),
            'cuentas_con_diferencia' => $diferencias->count(),
            'cuadra' => $diferencias->isEmpty(),
        ];
    }

    private function saldoNatural(NaturalezaCuenta $naturaleza, Dinero $debitos, Dinero $creditos): Dinero
    {
        return $naturaleza === NaturalezaCuenta::Debito ? $debitos->restar($creditos) : $creditos->restar($debitos);
    }
}
