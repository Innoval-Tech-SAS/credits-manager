import type { ApiThirdPartyDocument } from '../types/api-third-party-document';

export const DOCUMENT_STATUS_BADGE_CLASSES: Record<ApiThirdPartyDocument['estado'], string> = {
    pendiente: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
    cancelado: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
};
