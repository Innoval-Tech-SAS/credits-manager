/** Raw shape of `resumen_cuotas`, devuelto por `GET /creditos/{id}` y `GET /creditos/{id}/estado-cuenta`. */
export type ApiResumenCuotas = {
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    interes_pendiente: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo_capital_pendiente: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo_capital_pagado: string;
    /** Curva teórica de `AmortizacionCalculator`, indexada por `numero_cuota`. Valores como decimal string. */
    saldo_capital_teorico_por_cuota: Record<number, string>;
};
