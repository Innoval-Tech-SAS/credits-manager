<?php

namespace App\Creditos\Services;

use App\Core\Dinero\Dinero;
use App\Creditos\Enums\TipoPago;
use DateTime;

/**
 * Sistema francés (cuota fija, interés compuesto sobre saldo insoluto). Cálculo puro,
 * sin acceso a base de datos — misma fórmula y mismo orden de redondeo que el legado
 * (sistema-creditos/includes/functions.php::generarCuotas). No modificar sin verificar
 * contra el legado: la última cuota absorbe el redondeo acumulado.
 *
 * El float solo vive en el cálculo del factor de la cuota nivelada y en la
 * multiplicación saldo × tasa (`$i`) — es la tasa de interés, no dinero. En el momento
 * en que un número se convierte en "la cuota", "el interés del período" o "el capital
 * del período" se redondea una única vez a centavo vía `Dinero`, nunca se acumula
 * error de flotante en sumas/restas posteriores (esas son aritmética entera exacta).
 */
class AmortizacionCalculator
{
    public static function calcular(
        Dinero $valorPrestamo,
        float $tasaInteres,
        int $plazo,
        TipoPago $tipoPago,
        string $fechaDesembolso,
    ): array {
        $i = $tasaInteres / 100;

        if ($i > 0) {
            $factorCuota = $i / (1 - (1 + $i) ** -$plazo);
            $valorCuota = $valorPrestamo->multiplicarPorTasa($factorCuota);
        } else {
            $valorCuota = $valorPrestamo->dividirEntre($plazo);
        }

        $fechaBase = new DateTime($fechaDesembolso);
        $saldo = $valorPrestamo;
        $valorTotal = Dinero::cero();
        $cuotas = [];

        for ($n = 1; $n <= $plazo; $n++) {
            $fechaCuota = clone $fechaBase;

            if ($tipoPago === TipoPago::Mensual) {
                $fechaCuota->modify("+{$n} month");
            } else {
                $dias = $n * 15;
                $fechaCuota->modify("+{$dias} day");
            }

            $interesPeriodo = $saldo->multiplicarPorTasa($i);

            if ($n === $plazo) {
                $capitalPeriodo = $saldo;
                $valorEstaCuota = $capitalPeriodo->sumar($interesPeriodo);
            } else {
                $capitalPeriodo = $valorCuota->restar($interesPeriodo);
                $valorEstaCuota = $valorCuota;
            }

            $saldo = $saldo->restar($capitalPeriodo);
            $valorTotal = $valorTotal->sumar($valorEstaCuota);

            $cuotas[] = [
                'numero_cuota' => $n,
                'fecha_vencimiento' => $fechaCuota->format('Y-m-d'),
                'valor' => $valorEstaCuota,
                'valor_interes' => $interesPeriodo,
                'valor_capital' => $capitalPeriodo,
                'saldo_capital' => $saldo->noNegativo(),
            ];
        }

        return [
            'valor_cuota' => $valorCuota,
            'valor_total' => $valorTotal,
            'cuotas' => $cuotas,
        ];
    }
}
