import type { StatCardAccent } from '../types/stat-card-accent';

export const STAT_CARD_ACCENT_CLASSES: Record<StatCardAccent, string> = {
    neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90',
    success: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
    error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
};
