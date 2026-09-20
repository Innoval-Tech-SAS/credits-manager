import type { ApiClient } from '../types/api-client';

export type ClientSummaryCardsProps = {
    cliente: ApiClient;
    saldoConsolidado: number;
    creditosCount: number;
};
