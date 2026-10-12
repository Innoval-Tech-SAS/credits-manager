import type { ChangeEvent, FocusEvent } from 'react';

export type TextAreaProps = {
    id?: string;
    name?: string;
    placeholder?: string;
    rows?: number;
    value?: string;
    onChange?: (event: ChangeEvent<HTMLTextAreaElement>) => void;
    onFocus?: (event: FocusEvent<HTMLTextAreaElement>) => void;
    className?: string;
    minLength?: number;
    maxLength?: number;
    disabled?: boolean;
    required?: boolean;
    success?: boolean;
    error?: boolean;
    hint?: string;
};
