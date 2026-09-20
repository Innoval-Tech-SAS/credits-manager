import type { ApiInstallment } from '../types/api-installment';

export type CreditInstallmentsTableProps = {
    cuotas: ApiInstallment[];
    saldoCapitalTeoricoPorCuota: Record<number, string>;
};
