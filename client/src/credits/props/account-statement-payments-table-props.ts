import type { ApiPayment } from '../../payments/types/api-payment';

export type AccountStatementPaymentsTableProps = {
    pagos: ApiPayment[];
    totalPagado: number;
};
