<?php

namespace App\Pagos\Services;

use App\Core\Dinero\Dinero;

/**
 * Regla de aplicación de pagos: a las cuotas pendientes en orden cronológico, primero interés y luego capital.
 * Función pura sobre fotografías de cuotas: la usan el pago normal y la importación histórica (validación simulada
 * y escritura), así ambas toman exactamente la misma decisión.
 */
class AplicadorPagoPlan
{
    /**
     * @param  list<array{valor: Dinero, valor_pagado: Dinero, valor_interes: Dinero}>  $cuotas  ya ordenadas
     * @return array{asignaciones: list<array{posicion: int, aplicado: Dinero, interes: Dinero, capital: Dinero, nuevo_pagado: Dinero}>, sobrante: Dinero, capital: Dinero, interes: Dinero}
     */
    public static function aplicar(array $cuotas, Dinero $valor): array
    {
        $restante = $valor;
        $capital = Dinero::cero();
        $interes = Dinero::cero();
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

            // El desglose sale de la cuota fija (AmortizacionCalculator), no de pago_detalle.
            $interesYaPagado = $cuota['valor_pagado']->menorEntre($cuota['valor_interes']);
            $interesPendiente = $cuota['valor_interes']->restar($interesYaPagado);
            $interesEnEstePago = $aplicar->menorEntre($interesPendiente->noNegativo());
            $capitalEnEstePago = $aplicar->restar($interesEnEstePago);

            $capital = $capital->sumar($capitalEnEstePago);
            $interes = $interes->sumar($interesEnEstePago);

            $asignaciones[] = [
                'posicion' => $posicion,
                'aplicado' => $aplicar,
                'interes' => $interesEnEstePago,
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
        ];
    }
}
