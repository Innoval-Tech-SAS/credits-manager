import type { ApiOutflowPaymentMethod } from '../types/api-outflow';

export const OUTFLOW_PAYMENT_METHOD_LABELS: Record<ApiOutflowPaymentMethod, string> = {
    efectivo: 'Efectivo',
    transferencia: 'Transferencia',
    consignacion: 'Consignación',
    cheque: 'Cheque',
};

export const OUTFLOW_PAYMENT_METHOD_OPTIONS = (Object.keys(OUTFLOW_PAYMENT_METHOD_LABELS) as ApiOutflowPaymentMethod[]).map((valor) => ({
    value: valor,
    label: OUTFLOW_PAYMENT_METHOD_LABELS[valor],
}));
