import type { FC } from 'react';

import type { TextAreaProps } from '../props/text-area-props';

export const TextArea: FC<TextAreaProps> = ({
    id,
    name,
    placeholder,
    rows = 3,
    value,
    onChange,
    onFocus,
    className = '',
    minLength,
    maxLength,
    disabled = false,
    required = false,
    success = false,
    error = false,
    hint,
}) => {
    let textAreaClasses = `w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 ${className}`;

    if (disabled) {
        textAreaClasses += ` text-gray-500 border-gray-300 opacity-40 bg-gray-100 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700`;
    } else if (error) {
        textAreaClasses += ` border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:text-error-400 dark:border-error-500 dark:focus:border-error-800`;
    } else if (success) {
        textAreaClasses += ` border-success-500 focus:border-success-300 focus:ring-success-500/20 dark:text-success-400 dark:border-success-500 dark:focus:border-success-800`;
    } else {
        textAreaClasses += ` bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800`;
    }

    return (
        <div>
            <textarea
                id={id}
                name={name}
                placeholder={placeholder}
                rows={rows}
                value={value}
                onChange={onChange}
                onFocus={onFocus}
                minLength={minLength}
                maxLength={maxLength}
                disabled={disabled}
                required={required}
                className={textAreaClasses}
            />

            {hint && (
                <p
                    className={`mt-1.5 text-xs ${
                        error ? 'text-error-500' : success ? 'text-success-500' : 'text-gray-500 dark:text-gray-400'
                    }`}
                >
                    {hint}
                </p>
            )}
        </div>
    );
};
