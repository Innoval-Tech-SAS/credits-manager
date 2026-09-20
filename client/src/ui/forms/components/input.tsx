import type { FC } from 'react';

import type { InputProps } from '../props/input-props';

export const Input: FC<InputProps> = ({
    type = 'text',
    id,
    name,
    autoComplete,
    placeholder,
    value,
    onChange,
    onFocus,
    className = '',
    min,
    max,
    step,
    minLength,
    maxLength,
    disabled = false,
    required = false,
    success = false,
    error = false,
    hint,
    startIcon,
    endIcon,
    pill = false,
}) => {
    let inputClasses = `h-11 w-full border appearance-none py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 ${
        pill ? 'rounded-xl' : 'rounded-lg'
    } ${startIcon ? 'pl-11' : 'pl-4'} ${endIcon ? 'pr-11' : 'pr-4'} ${className}`;

    if (disabled) {
        inputClasses += ` text-gray-500 border-gray-300 opacity-40 bg-gray-100 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700`;
    } else if (error) {
        inputClasses += ` border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:text-error-400 dark:border-error-500 dark:focus:border-error-800`;
    } else if (success) {
        inputClasses += ` border-success-500 focus:border-success-300 focus:ring-success-500/20 dark:text-success-400 dark:border-success-500 dark:focus:border-success-800`;
    } else {
        inputClasses += ` bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800`;
    }

    return (
        <div>
            <div className="relative">
                {startIcon && (
                    <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        {startIcon}
                    </span>
                )}
                <input
                    type={type}
                    id={id}
                    name={name}
                    autoComplete={autoComplete}
                    placeholder={placeholder}
                    value={value}
                    onChange={onChange}
                    onFocus={onFocus}
                    min={min}
                    max={max}
                    step={step}
                    minLength={minLength}
                    maxLength={maxLength}
                    disabled={disabled}
                    required={required}
                    className={inputClasses}
                />
                {endIcon && (
                    <span className="absolute top-1/2 right-4 -translate-y-1/2">{endIcon}</span>
                )}
            </div>

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
