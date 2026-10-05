import type { ApiInvoicePayment } from '../types/api-invoice';

export type InvoicePaymentsTableProps = {
    cobros: ApiInvoicePayment[];
    /** Solo quien puede anular ve la acción de reversar. */
    puedeReversar: boolean;
    onReversar: (cobro: ApiInvoicePayment) => void;
};
