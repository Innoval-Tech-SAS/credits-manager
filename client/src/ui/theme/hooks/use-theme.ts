import { createContext, useContext } from 'react';

import type { ThemeContextValue } from '../types/theme';

export const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

export const useTheme = (): ThemeContextValue => {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }

    return context;
};
