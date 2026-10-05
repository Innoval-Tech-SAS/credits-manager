export const IMPORT_STATUSES = ['pendiente', 'validando', 'pendiente_decision', 'importando', 'completada', 'fallida', 'cancelada'] as const;

export type ImportStatus = (typeof IMPORT_STATUSES)[number];

export const IMPORT_STATUS_LABELS: Record<ImportStatus, string> = {
    pendiente: 'En cola',
    validando: 'Validando',
    pendiente_decision: 'Espera tu decisión',
    importando: 'Escribiendo',
    completada: 'Completada',
    fallida: 'Fallida',
    cancelada: 'Cancelada',
};

export const IMPORT_STATUS_BADGE_CLASSES: Record<ImportStatus, string> = {
    pendiente: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
    validando: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
    pendiente_decision: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
    importando: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
    completada: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    fallida: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
    cancelada: 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
};

/** Estados en los que el proceso sigue corriendo en el worker: la pantalla vuelve a consultar hasta que cambien. */
export const IMPORT_TRANSITIONAL_STATUSES: ImportStatus[] = ['pendiente', 'validando', 'importando'];
