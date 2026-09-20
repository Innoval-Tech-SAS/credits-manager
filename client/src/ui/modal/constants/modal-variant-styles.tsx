import { CircleCheck, Info, TriangleAlert } from 'lucide-react';
import type { ModalVariant } from '../types/modal-variant';
import type { ModalVariantStyle } from '../types/modal-variant-style';

export const MODAL_VARIANT_STYLES: Record<ModalVariant, ModalVariantStyle> = {
    danger: {
        iconWrapperClass: 'bg-error-50 dark:bg-error-500/15',
        iconClass: 'text-error-600 dark:text-error-500',
        confirmButtonClass: 'bg-error-500 hover:bg-error-600',
        Icon: TriangleAlert,
    },
    warning: {
        iconWrapperClass: 'bg-warning-50 dark:bg-warning-500/15',
        iconClass: 'text-warning-600 dark:text-orange-400',
        confirmButtonClass: 'bg-warning-500 hover:bg-warning-600',
        Icon: TriangleAlert,
    },
    info: {
        iconWrapperClass: 'bg-blue-light-50 dark:bg-blue-light-500/15',
        iconClass: 'text-blue-light-600 dark:text-blue-light-500',
        confirmButtonClass: 'bg-blue-light-500 hover:bg-blue-light-600',
        Icon: Info,
    },
    success: {
        iconWrapperClass: 'bg-success-50 dark:bg-success-500/15',
        iconClass: 'text-success-600 dark:text-success-500',
        confirmButtonClass: 'bg-success-500 hover:bg-success-600',
        Icon: CircleCheck,
    },
};
