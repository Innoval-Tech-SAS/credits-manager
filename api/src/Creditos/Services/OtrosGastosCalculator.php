<?php

namespace App\Creditos\Services;

use App\Core\Dinero\Dinero;
use App\Creditos\Enums\TipoPago;

/**
 * Otros gastos (gastos de administración). Función única para crédito y refinanciación:
 * ambos pasan por aquí con su capital inicial. Nunca toca capital ni interés.
 */
class OtrosGastosCalculator
{
    /** Capital inicial × porcentaje: el mismo valor en todas las cuotas, incluida la última. */
    public static function valorPorCuota(Dinero $capitalInicial, float $porcentaje): Dinero
    {
        return $capitalInicial->multiplicarPorTasa($porcentaje / 100);
    }

    /**
     * Parte de un valor de periodo devengada tras $dias: días / días del periodo, topado en 1.
     * Mismo conteo que la liquidación anticipada (tasa ÷ 30 o ÷ 15 según el tipo de pago).
     */
    public static function proporcionalPorDias(Dinero $valorPeriodo, int $dias, TipoPago $tipoPago): Dinero
    {
        $divisor = $tipoPago->diasPorPeriodo();
        $diasDevengados = max(0, min($dias, $divisor));

        return $valorPeriodo->multiplicarPorTasa($diasDevengados / $divisor);
    }
}
