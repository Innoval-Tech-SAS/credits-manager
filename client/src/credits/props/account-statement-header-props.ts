import type { Company } from '../../company/types/company';
import type { ApiCredit } from '../types/api-credit';

export type AccountStatementHeaderProps = {
    company: Company | null;
    credito: ApiCredit;
    cuotasPagadas: number;
};
