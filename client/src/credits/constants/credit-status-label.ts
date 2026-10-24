import type { ApiCredit } from '../types/api-credit';

export const CREDIT_STATUS_LABEL: Record<ApiCredit['estado'], string> = {
    solicitado: 'Solicitado',
    aprobado: 'Aprobado',
    desembolsado: 'Desembolsado',
    pagado: 'Pagado',
    cancelado: 'Cancelado',
    refinanciado: 'Refinanciado',
};
