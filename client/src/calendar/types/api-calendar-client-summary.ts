import type { ApiPayment } from '../../payments/types/api-payment';

/** Parte de `GET /clientes/{id}/resumen` que usa el panel del cliente en el calendario. */
export type ApiCalendarClientSummary = {
    /** Decimal string, viene de `App\Core\Dinero\Dinero`. */
    saldo_consolidado: string;
    cartera: {
        /** Decimal string: suma de las cuotas en mora de todos los créditos del cliente. */
        saldo_en_mora: string;
        cuotas_vencidas: number;
        dias_mora: number;
    };
    ultimos_pagos: ApiPayment[];
};
