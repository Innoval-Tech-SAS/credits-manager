<?php

namespace App\Pagos\Services;

use App\Core\Dinero\Dinero;

/**
 * Regla de aplicación de pagos: a las cuotas pendientes en orden cronológico, primero interés, luego otros gastos y
 * por último capital. Función pura sobre fotografías de cuotas: la usan el pago normal y la importación histórica
 * (validación simulada y escritura), así ambas toman exactamente la misma decisión.
 */
class AplicadorPagoPlan
{
    /**
     * Reparto de lo ya pagado de una cuota en sus tres componentes, con el mismo orden de aplicación.
     *
     * @return array{interes: Dinero, otros: Dinero, capital: Dinero}
     */
    public static function desglosePagado(Dinero $pagado, Dinero $interes, Dinero $otros): array
    {
        $interesPagado = $pagado->menorEntre($interes);
        $otrosPagados = $pagado->restar($interesPagado)->noNegativo()->menorEntre($otros);
        $capitalPagado = $pagado->restar($interesPagado)->restar($otrosPagados)->noNegativo();

        return ['interes' => $interesPagado, 'otros' => $otrosPagados, 'capital' => $capitalPagado];
    }

    /**
     * @param  list<array{valor: Dinero, valor_pagado: Dinero, valor_interes: Dinero, valor_otros_gastos: Dinero}>  $cuotas  ya ordenadas; `valor` es el total exigible (capital + interés + otros)
     * @return array{asignaciones: list<array{posicion: int, aplicado: Dinero, interes: Dinero, otros: Dinero, capital: Dinero, nuevo_pagado: Dinero}>, sobrante: Dinero, capital: Dinero, interes: Dinero, otros: Dinero}
     */
    public static function aplicar(array $cuotas, Dinero $valor): array
    {
        $restante = $valor;
        $capital = Dinero::cero();
        $interes = Dinero::cero();
        $otros = Dinero::cero();
        $asignaciones = [];

        foreach ($cuotas as $posicion => $cuota) {
            if (! $restante->esPositivo()) {
                break;
            }

            $saldoCuota = $cuota['valor']->restar($cuota['valor_pagado']);
            if (! $saldoCuota->esPositivo()) {
                continue;
            }

            $aplicar = $restante->menorEntre($saldoCuota);

            // El desglose sale de la cuota fija (AmortizacionCalculator + otros gastos), no de pago_detalle.
            $pendiente = self::desglosePagado($cuota['valor_pagado'], $cuota['valor_interes'], $cuota['valor_otros_gastos']);
            $interesPendiente = $cuota['valor_interes']->restar($pendiente['interes']);
            $otrosPendientes = $cuota['valor_otros_gastos']->restar($pendiente['otros']);

            $interesEnEstePago = $aplicar->menorEntre($interesPendiente->noNegativo());
            $otrosEnEstePago = $aplicar->restar($interesEnEstePago)->menorEntre($otrosPendientes->noNegativo());
            $capitalEnEstePago = $aplicar->restar($interesEnEstePago)->restar($otrosEnEstePago);

            $capital = $capital->sumar($capitalEnEstePago);
            $interes = $interes->sumar($interesEnEstePago);
            $otros = $otros->sumar($otrosEnEstePago);

            $asignaciones[] = [
                'posicion' => $posicion,
                'aplicado' => $aplicar,
                'interes' => $interesEnEstePago,
                'otros' => $otrosEnEstePago,
                'capital' => $capitalEnEstePago,
                'nuevo_pagado' => $cuota['valor_pagado']->sumar($aplicar),
            ];

            $restante = $restante->restar($aplicar);
        }

        return [
            'asignaciones' => $asignaciones,
            'sobrante' => $restante,
            'capital' => $capital,
            'interes' => $interes,
            'otros' => $otros,
        ];
    }
}
