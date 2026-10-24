import type { SpinnerColor } from '../types/spinner-color';

export const SPINNER_COLOR_CLASSES: Record<SpinnerColor, string> = {
    brand: 'border-gray-200 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400',
    gray: 'border-gray-100 border-t-gray-500 dark:border-gray-800 dark:border-t-gray-300',
    white: 'border-white/30 border-t-white',
};
