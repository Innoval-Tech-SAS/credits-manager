import type { ApiInvoiceNoteCreditReason, ApiInvoiceNoteDebitReason } from '../types/api-invoice';

export const NOTE_CREDIT_REASON_LABELS: Record<ApiInvoiceNoteCreditReason, string> = {
    devolucion: 'Devolución',
    anulacion: 'Anulación',
    rebaja_descuento: 'Rebaja o descuento',
    ajuste_precio: 'Ajuste de precio',
    otros: 'Otros',
};

export const NOTE_DEBIT_REASON_LABELS: Record<ApiInvoiceNoteDebitReason, string> = {
    intereses: 'Intereses',
    gastos_por_cobrar: 'Gastos por cobrar',
    cambio_valor: 'Cambio del valor',
    otros: 'Otros',
};

export const NOTE_CREDIT_REASON_OPTIONS = (Object.keys(NOTE_CREDIT_REASON_LABELS) as ApiInvoiceNoteCreditReason[]).map((valor) => ({
    value: valor,
    label: NOTE_CREDIT_REASON_LABELS[valor],
}));

export const NOTE_DEBIT_REASON_OPTIONS = (Object.keys(NOTE_DEBIT_REASON_LABELS) as ApiInvoiceNoteDebitReason[]).map((valor) => ({
    value: valor,
    label: NOTE_DEBIT_REASON_LABELS[valor],
}));
