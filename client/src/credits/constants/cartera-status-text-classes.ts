import type { ApiCreditDetail } from '../types/api-credit-detail';

export const CARTERA_STATUS_TEXT_CLASSES: Record<ApiCreditDetail['cartera'], string> = {
    'En mora': 'text-error-600 dark:text-error-400',
    'Al día': 'text-success-600 dark:text-success-400',
    Refinanciado: 'text-blue-light-600 dark:text-blue-light-400',
};
