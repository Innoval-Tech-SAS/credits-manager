import type { FC } from 'react';
import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import type { DashboardFilterSelectProps } from '../props/dashboard-filter-select-props';

/** Reemplaza al `<select>` nativo: su lista la pinta el navegador y no admite el scroll del proyecto. */
export const DashboardFilterSelect: FC<DashboardFilterSelectProps> = ({ options, value, onChange, placeholder }) => {
    const [isOpen, setIsOpen] = useState(false);
    const seleccionada = options.find((option) => option.value === value);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen((abierto) => !abierto)}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
                className="dropdown-toggle flex h-11 w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-transparent px-4 text-left text-sm shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:focus:border-brand-800"
            >
                <span className={`truncate ${seleccionada ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-white/30'}`}>
                    {seleccionada?.label ?? placeholder}
                </span>
                <ChevronDown className={`size-4 shrink-0 text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
            </button>

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="left-0 max-h-72 w-full overflow-y-auto custom-scrollbar p-1">
                {options.map((option) => (
                    <DropdownItem
                        key={option.value}
                        onClick={() => onChange(option.value)}
                        onItemClick={() => setIsOpen(false)}
                        className={`flex w-full rounded-lg text-left text-sm hover:bg-gray-100 dark:hover:bg-white/5 ${option.value === value ? 'font-medium text-brand-500 dark:text-brand-400' : 'text-gray-700 dark:text-gray-300'}`}
                    >
                        {option.label}
                    </DropdownItem>
                ))}
            </Dropdown>
        </div>
    );
};
