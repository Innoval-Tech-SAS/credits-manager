import type { FC } from 'react';
import { useEffect, useRef } from 'react';

import { Check, Minus } from 'lucide-react';
import type { CheckboxProps } from '../props/checkbox-props';

export const Checkbox: FC<CheckboxProps> = ({ label, ariaLabel, checked, indeterminate = false, id, onChange, className = '', disabled = false }) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const isMixed = indeterminate && !checked;

    // `indeterminate` no es un atributo HTML: solo se puede fijar como propiedad del elemento.
    useEffect(() => {
        if (inputRef.current) {
            inputRef.current.indeterminate = isMixed;
        }
    }, [isMixed]);

    return (
        <label className={`flex items-center space-x-3 group cursor-pointer ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}>
            <div className="relative w-5 h-5 shrink-0">
                <input
                    ref={inputRef}
                    id={id}
                    type="checkbox"
                    aria-checked={isMixed ? 'mixed' : checked}
                    aria-label={ariaLabel}
                    className={`w-5 h-5 appearance-none cursor-pointer dark:border-gray-700 border border-gray-300 checked:border-transparent rounded-md checked:bg-brand-500 disabled:opacity-60 ${isMixed ? 'border-transparent bg-brand-500 dark:border-transparent' : ''} ${className}`}
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    disabled={disabled}
                />
                {checked ? (
                    <Check className="pointer-events-none absolute top-1/2 left-1/2 size-4 -translate-x-1/2 -translate-y-1/2 text-white" />
                ) : isMixed ? (
                    <Minus className="pointer-events-none absolute top-1/2 left-1/2 size-4 -translate-x-1/2 -translate-y-1/2 text-white" />
                ) : null}
            </div>
            {label && <span className="text-sm font-medium text-gray-800 dark:text-gray-200">{label}</span>}
        </label>
    );
};
