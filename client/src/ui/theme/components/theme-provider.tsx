import type { FC } from 'react';
import { useEffect, useState } from 'react';

import { ThemeContext } from '../hooks/use-theme';
import type { Theme } from '../types/theme';
import { readStoredTheme } from '../utils/read-stored-theme';
import type { WithChildrenProps } from '../../../shared/props/with-children-props';

export const ThemeProvider: FC<WithChildrenProps> = ({ children }) => {
    const [theme, setTheme] = useState<Theme>(readStoredTheme);

    useEffect(() => {
        localStorage.setItem('theme', theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');
    }, [theme]);

    const toggleTheme = () => {
        setTheme((previous) => (previous === 'light' ? 'dark' : 'light'));
    };

    return <ThemeContext.Provider value={{ theme, toggleTheme }}>{children}</ThemeContext.Provider>;
};
