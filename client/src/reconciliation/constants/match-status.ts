import type { ApiExtractoMovimiento, ApiSugerenciaConciliacion } from '../types/api-match-conciliacion';

export const MOVEMENT_MATCH_STATUS_LABELS: Record<ApiExtractoMovimiento['estado'], string> = {
    pendiente: 'Pendiente',
    conciliado: 'Conciliado',
    puente: 'Cuenta puente',
};

export const MOVEMENT_MATCH_STATUS_BADGE_CLASSES: Record<ApiExtractoMovimiento['estado'], string> = {
    pendiente: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
    conciliado: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
    puente: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
};

export const SUGGESTION_STATUS_LABELS: Record<ApiSugerenciaConciliacion['estado_sugerido'], string> = {
    exacto: 'Candidato exacto',
    duplicado: 'Varios candidatos',
    con_diferencia: 'Sin valor exacto',
    sin_candidato: 'Ninguno',
};

export const SUGGESTION_STATUS_BADGE_CLASSES: Record<ApiSugerenciaConciliacion['estado_sugerido'], string> = {
    exacto: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
    duplicado: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
    con_diferencia: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
    sin_candidato: 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400',
};
