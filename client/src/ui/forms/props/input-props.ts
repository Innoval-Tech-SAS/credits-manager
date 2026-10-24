import type { ChangeEvent, FocusEvent, ReactNode } from 'react';

export type InputProps = {
    startIcon?: ReactNode;
    endIcon?: ReactNode;
    pill?: boolean;
    type?: 'text' | 'number' | 'email' | 'password' | 'date' | 'time' | string;
    id?: string;
    name?: string;
    autoComplete?: string;
    placeholder?: string;
    value?: string | number;
    onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
    onFocus?: (event: FocusEvent<HTMLInputElement>) => void;
    className?: string;
    min?: string;
    max?: string;
    step?: number;
    minLength?: number;
    maxLength?: number;
    disabled?: boolean;
    required?: boolean;
    success?: boolean;
    error?: boolean;
    hint?: string;
    /** `md` (por defecto) es el tamaño de siempre; `sm` achica alto y relleno para barras de filtros densas. */
    size?: 'sm' | 'md';
};
