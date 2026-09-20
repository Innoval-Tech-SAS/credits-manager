import type { Company } from './company';

export type CompanyContextValue = {
    company: Company | null;
    isLoading: boolean;
    refresh: () => Promise<void>;
};
