import type { ApiInvoicePaymentMethod } from '../types/api-invoice';

export const PAYMENT_METHOD_LABELS: Record<ApiInvoicePaymentMethod, string> = {
    efectivo: 'Efectivo',
    transferencia: 'Transferencia',
    consignacion: 'Consignación',
    cheque: 'Cheque',
    tarjeta: 'Tarjeta',
};

export const PAYMENT_METHOD_OPTIONS = (Object.keys(PAYMENT_METHOD_LABELS) as ApiInvoicePaymentMethod[]).map((valor) => ({
    value: valor,
    label: PAYMENT_METHOD_LABELS[valor],
}));
