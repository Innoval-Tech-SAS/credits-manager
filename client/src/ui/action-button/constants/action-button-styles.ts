import type { ActionButtonVariant } from '../props/action-button-props';

export const ACTION_BUTTON_VARIANT_CLASSES: Record<ActionButtonVariant, string> = {
    default: 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90',
    danger: 'text-gray-500 hover:text-error-500 dark:text-gray-400 dark:hover:text-error-500',
    warning: 'text-gray-500 hover:text-warning-500 dark:text-gray-400 dark:hover:text-warning-500',
};
