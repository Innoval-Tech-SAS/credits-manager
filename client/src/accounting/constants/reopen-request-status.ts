import type { ApiReopenRequest } from '../types/api-reopen-request';

export const REOPEN_REQUEST_STATUS_LABEL: Record<ApiReopenRequest['estado'], string> = {
    pendiente: 'Pendiente',
    autorizada: 'Autorizada',
    rechazada: 'Rechazada',
};

export const REOPEN_REQUEST_STATUS_BADGE_CLASSES: Record<ApiReopenRequest['estado'], string> = {
    pendiente: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400',
    autorizada: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    rechazada: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
};
