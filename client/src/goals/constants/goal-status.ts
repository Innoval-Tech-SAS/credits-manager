import type { ApiGoalStatus, ApiGoalType } from '../types/api-goals-period';

export const GOAL_TYPE_LABELS: Record<ApiGoalType, string> = {
    colocacion: 'Colocación',
    recaudo: 'Recaudo',
};

export const GOAL_STATUS_LABELS: Record<ApiGoalStatus, string> = {
    en_curso: 'En curso',
    alcanzada: 'Alcanzada',
    en_riesgo: 'En riesgo',
    cumplida: 'Cumplida',
    cumplida_parcial: 'Cumplida parcial',
    incumplida: 'Incumplida',
};

/** Semáforo: éxito si se llegó o se cumplió, advertencia si va en riesgo o quedó parcial, error si se incumplió, marca si va en curso. */
export const GOAL_STATUS_BADGE_CLASSES: Record<ApiGoalStatus, string> = {
    en_curso: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    alcanzada: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
    cumplida: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
    en_riesgo: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
    cumplida_parcial: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
    incumplida: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
};
