import type { ApiPayment } from '../types/api-payment';

export type PaymentHistoryTableProps = {
    pagos: ApiPayment[];
    /** Muestra la columna "Crédito #" con link — para vistas donde los pagos pueden venir de varios créditos (p. ej. detalle de cliente). */
    showCredito?: boolean;
    onViewCredito?: (creditoId: number) => void;
};
