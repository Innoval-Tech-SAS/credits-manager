import type { ServiceStatus } from '../types/service-status';

export const SERVICE_STATUS_LABELS: Record<ServiceStatus, string> = {
    activo: 'Activo',
    ocupado: 'Procesando',
    caido: 'Caído',
};

export const SERVICE_STATUS_CLASSES: Record<ServiceStatus, string> = {
    activo: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    ocupado: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
    caido: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
};

export const SERVICE_STATUS_DOT_CLASSES: Record<ServiceStatus, string> = {
    activo: 'bg-success-500',
    ocupado: 'bg-brand-500',
    caido: 'bg-error-500',
};
