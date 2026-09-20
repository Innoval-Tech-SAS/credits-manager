import type { FC } from 'react';
import { useState } from 'react';

import { Dropdown } from '@ui/dropdown/components/dropdown';
import { Bell } from 'lucide-react';

export const NotificationDropdown: FC = () => {
    const [isOpen, setIsOpen] = useState(false);

    const toggleDropdown = () => setIsOpen((previous) => !previous);
    const closeDropdown = () => setIsOpen(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={toggleDropdown}
                aria-label="Notificaciones"
                className="dropdown-toggle flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                <Bell className="size-5 fill-current" />
            </button>

            <Dropdown isOpen={isOpen} onClose={closeDropdown} className="mt-4.25 flex w-80 flex-col p-4">
                <h5 className="mb-3 border-b border-gray-100 pb-3 text-sm font-semibold text-gray-800 dark:border-gray-700 dark:text-gray-200">
                    Notificaciones
                </h5>
                <p className="py-6 text-center text-sm text-gray-400 dark:text-gray-500">No tienes notificaciones.</p>
            </Dropdown>
        </div>
    );
};
