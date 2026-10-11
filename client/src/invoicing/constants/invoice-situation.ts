import type { ApiInvoiceSituation } from '../types/api-invoice';

export const INVOICE_SITUATION_LABELS: Record<ApiInvoiceSituation, string> = {
    emitida: 'Emitida',
    parcialmente_pagada: 'Parcialmente pagada',
    pagada: 'Pagada',
    con_saldo_a_favor: 'Con saldo a favor',
    anulada: 'Anulada',
};

/** Colores semánticos de TailAdmin: pagada = success, parcial/saldo a favor = warning (pendiente de resolver), anulada = error, emitida = brand. */
export const INVOICE_SITUATION_BADGE_CLASSES: Record<ApiInvoiceSituation, string> = {
    emitida: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    parcialmente_pagada: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    pagada: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    con_saldo_a_favor: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    anulada: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
};
