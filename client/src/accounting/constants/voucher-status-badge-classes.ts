import type { ApiVoucher } from '../types/api-voucher';

export const VOUCHER_STATUS_BADGE_CLASSES: Record<ApiVoucher['estado'], string> = {
    contabilizado: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    anulado: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
    borrador: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
};
