import type { ApiCredit } from '../types/api-credit';

export const CREDIT_STATUS_BADGE_CLASSES: Record<ApiCredit['estado'], string> = {
    solicitado: 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
    aprobado: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    desembolsado: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    pagado: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
    cancelado: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
    refinanciado: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
};
