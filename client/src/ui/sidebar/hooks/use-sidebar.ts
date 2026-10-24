import { createContext, useContext } from 'react';

import type { SidebarContextValue } from '../types/sidebar-context-value';

export const SidebarContext = createContext<SidebarContextValue | undefined>(undefined);

export const useSidebar = (): SidebarContextValue => {
    const context = useContext(SidebarContext);

    if (!context) {
        throw new Error('useSidebar must be used within a SidebarProvider');
    }

    return context;
};
