import { createContext, useContext } from 'react';

import type { CompanyContextValue } from '../types/company-context-value';

export const CompanyContext = createContext<CompanyContextValue | undefined>(undefined);

export const useCompany = (): CompanyContextValue => {
    const context = useContext(CompanyContext);

    if (!context) {
        throw new Error('useCompany must be used within a CompanyProvider');
    }

    return context;
};
