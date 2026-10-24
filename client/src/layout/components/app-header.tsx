import type { FC } from 'react';

import { Menu } from 'lucide-react';
import { useSidebar } from '@ui/sidebar/hooks/use-sidebar';
import { ThemeToggleButton } from '@ui/theme/components/theme-toggle-button';
import { NotificationDropdown } from './notification-dropdown';
import { UserDropdown } from './user-dropdown';

export const AppHeader: FC = () => {
    const { toggleSidebar, toggleMobileSidebar } = useSidebar();

    const handleToggle = () => {
        if (window.innerWidth >= 1024) {
            toggleSidebar();
        } else {
            toggleMobileSidebar();
        }
    };

    return (
        <header className="flex w-full shrink-0 border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:border-b">
            <div className="flex w-full items-center justify-between gap-4 px-4 py-4 lg:px-6">
                <button
                    className="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400"
                    onClick={handleToggle}
                    aria-label="Abrir/cerrar menú"
                >
                    <Menu className="size-5" />
                </button>

                <div className="flex items-center gap-3">
                    <ThemeToggleButton />
                    <NotificationDropdown />
                    <UserDropdown />
                </div>
            </div>
        </header>
    );
};
