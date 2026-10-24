import type { ApiCredit } from '../../credits/types/api-credit';
import type { ApiPayment } from '../../payments/types/api-payment';
import type { ApiClient } from './api-client';
import type { ApiClientDocument } from './api-client-document';
import type { ApiClientMonthlyBehavior } from './api-client-monthly-behavior';
import type { ApiEmploymentHistory } from './api-employment-history';

/** Raw shape returned by `GET /clientes/{id}/resumen`. */
export type ApiClientSummary = {
    cliente: ApiClient;
    creditos: ApiCredit[];
    saldo_consolidado: number;
    ultimos_pagos: ApiPayment[];
    documentos: ApiClientDocument[];
    historial_empleadores: ApiEmploymentHistory[];
    comportamiento_mensual: ApiClientMonthlyBehavior;
};
