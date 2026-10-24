import type { FC } from 'react';

import type { ToggleSwitchInputProps } from '../props/toggle-switch-input-props';

export const ToggleSwitchInput: FC<ToggleSwitchInputProps> = ({
    label,
    checked,
    onChange,
    disabled = false,
    id,
    className = '',
}) => {
    return (
        <label
            htmlFor={id}
            className={`flex cursor-pointer select-none items-center gap-3 text-sm font-medium ${
                disabled ? 'text-gray-400' : 'text-gray-700 dark:text-gray-400'
            } ${className}`}
        >
            <div className="relative">
                <input
                    id={id}
                    type="checkbox"
                    className="sr-only"
                    checked={checked}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <div
                    className={`block h-6 w-11 rounded-full transition duration-150 ease-linear ${
                        disabled
                            ? 'bg-gray-100 dark:bg-gray-800'
                            : checked
                              ? 'bg-brand-500'
                              : 'bg-gray-200 dark:bg-white/10'
                    }`}
                />
                <div
                    className={`absolute left-0.5 top-0.5 h-5 w-5 transform rounded-full bg-white shadow-theme-sm duration-150 ease-linear ${
                        checked ? 'translate-x-full' : 'translate-x-0'
                    }`}
                />
            </div>
            {label}
        </label>
    );
};
