import type { ApiInstallment } from '../types/api-installment';

export type AccountStatementInstallmentsTableProps = {
    cuotas: ApiInstallment[];
    saldoCapitalTeoricoPorCuota: Record<number, string>;
    saldo: string;
};
