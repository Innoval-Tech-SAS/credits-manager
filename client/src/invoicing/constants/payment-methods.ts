import type { ApiInvoicePaymentMethod } from '../types/api-invoice';

export const PAYMENT_METHOD_LABELS: Record<ApiInvoicePaymentMethod, string> = {
    efectivo: 'Efectivo',
    transferencia: 'Transferencia',
    consignacion: 'Consignación',
    cheque: 'Cheque',
    tarjeta: 'Tarjeta',
    // Lo fija el sistema al facturar intereses ya pagados (POST .../intereses-pagados): no es un medio elegible a mano.
    interes_ya_recaudado: 'Interés ya recaudado al pagar',
};

const MEDIOS_SELECCIONABLES: ApiInvoicePaymentMethod[] = ['efectivo', 'transferencia', 'consignacion', 'cheque', 'tarjeta'];

export const PAYMENT_METHOD_OPTIONS = MEDIOS_SELECCIONABLES.map((valor) => ({
    value: valor,
    label: PAYMENT_METHOD_LABELS[valor],
}));
