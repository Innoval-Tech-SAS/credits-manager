import type { ApiBankStatement } from '../types/api-bank-statement';

export const BANK_STATEMENT_STATUS_LABELS: Record<ApiBankStatement['estado'], string> = {
    cargando: 'Cargando',
    cargado: 'Cargado',
    rechazado: 'Rechazado',
    cerrado: 'Cerrado',
};

export const BANK_STATEMENT_STATUS_BADGE_CLASSES: Record<ApiBankStatement['estado'], string> = {
    cargando: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
    cargado: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
    rechazado: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
    cerrado: 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-400',
};
