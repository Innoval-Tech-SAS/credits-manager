import type { FC } from 'react';
import { useState } from 'react';

import { useAuth } from '../../auth/hooks/use-auth';
import { ChangeOwnPasswordModal } from './change-own-password-modal';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { ChevronDown, Lock, LogOut } from 'lucide-react';

export const UserDropdown: FC = () => {
    const { user, logout } = useAuth();
    const [isOpen, setIsOpen] = useState(false);
    const [isChangePasswordOpen, setIsChangePasswordOpen] = useState(false);

    if (!user) {
        return null;
    }

    const roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);

    const toggleDropdown = () => setIsOpen((previous) => !previous);
    const closeDropdown = () => setIsOpen(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={toggleDropdown}
                className="dropdown-toggle flex items-center gap-2 text-gray-700 dark:text-gray-400"
            >
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">
                    {user.name.charAt(0).toUpperCase()}
                </span>
                <span className="block text-left text-sm">
                    <span className="block font-medium text-gray-700 dark:text-gray-200">{user.name}</span>
                    <span className="block text-xs text-gray-400 dark:text-gray-500">{roleLabel}</span>
                </span>
                <ChevronDown className={`size-4 text-gray-500 transition-transform duration-200 dark:text-gray-400 ${isOpen ? 'rotate-180' : ''}`} />
            </button>

            <Dropdown isOpen={isOpen} onClose={closeDropdown} className="mt-4.25 flex w-55 flex-col p-3">
                <div className="border-b border-gray-200 pb-3 dark:border-gray-800">
                    <span className="block text-sm font-medium text-gray-700 dark:text-gray-200">{user.name}</span>
                    <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                        {roleLabel}
                    </span>
                </div>

                <ul className="flex flex-col gap-1 pt-3">
                    <li>
                        <DropdownItem
                            onItemClick={() => {
                                closeDropdown();
                                setIsChangePasswordOpen(true);
                            }}
                            className="group"
                        >
                            <Lock className="size-4 text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-300" />
                            Cambiar contraseña
                        </DropdownItem>
                    </li>
                    <li>
                        <DropdownItem
                            onItemClick={() => {
                                closeDropdown();
                                void logout();
                            }}
                            className="group"
                        >
                            <LogOut className="size-4 fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300" />
                            Cerrar sesión
                        </DropdownItem>
                    </li>
                </ul>
            </Dropdown>

            {isChangePasswordOpen && (
                <ChangeOwnPasswordModal onClose={() => setIsChangePasswordOpen(false)} />
            )}
        </div>
    );
};
