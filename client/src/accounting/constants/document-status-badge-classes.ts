import type { ApiCarteraDocument } from '../types/api-cartera-document';

export const DOCUMENT_STATUS_BADGE_CLASSES: Record<ApiCarteraDocument['estado'], string> = {
    pendiente: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
    abonado_parcial: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400',
    cancelado: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    vencido: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
};

export const DOCUMENT_STATUS_LABELS: Record<ApiCarteraDocument['estado'], string> = {
    pendiente: 'Pendiente',
    abonado_parcial: 'Abonado parcial',
    cancelado: 'Pagado',
    vencido: 'Vencido',
};
