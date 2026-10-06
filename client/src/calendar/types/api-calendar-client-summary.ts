import type { ApiPayment } from '../../payments/types/api-payment';

/** Parte de `GET /clientes/{id}/resumen` que usa el panel del cliente en el calendario. */
export type ApiCalendarClientSummary = {
    /** Decimal string, viene de `App\Core\Dinero\Dinero`. */
    saldo_consolidado: string;
    cartera: {
        cuotas_vencidas: number;
        dias_mora: number;
    };
    ultimos_pagos: ApiPayment[];
};
