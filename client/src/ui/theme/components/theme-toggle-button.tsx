import type { FC } from 'react';

import { Moon, Sun } from 'lucide-react';
import { useTheme } from '../hooks/use-theme';

export const ThemeToggleButton: FC = () => {
    const { toggleTheme } = useTheme();

    return (
        <button
            onClick={toggleTheme}
            aria-label="Cambiar tema"
            className="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <Moon className="hidden size-5 fill-current dark:block" />
            <Sun className="size-5 fill-current dark:hidden" />
        </button>
    );
};
