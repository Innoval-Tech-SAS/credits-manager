import { createContext, useContext } from 'react';

import type { PageHeaderContextValue } from '../types/page-header-context-value';

export const PageHeaderContext = createContext<PageHeaderContextValue | undefined>(undefined);

export const usePageHeader = (): PageHeaderContextValue => {
    const context = useContext(PageHeaderContext);

    if (!context) {
        throw new Error('usePageHeader must be used within a PageHeaderProvider');
    }

    return context;
};
