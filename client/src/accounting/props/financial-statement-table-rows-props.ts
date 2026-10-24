import type { ApiFinancialStatementRow } from '../types/api-financial-statements';

export type FinancialStatementTableRowsProps = {
    filas: ApiFinancialStatementRow[];
    negativo?: boolean;
};
