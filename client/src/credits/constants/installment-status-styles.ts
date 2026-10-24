export const INSTALLMENT_STATUS_LABEL: Record<string, string> = {
    pendiente: 'Pendiente',
    pagada: 'Pagada',
    vencida: 'Vencida',
};

export const INSTALLMENT_STATUS_CLASSES: Record<string, string> = {
    pendiente: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    pagada: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
    vencida: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
};
