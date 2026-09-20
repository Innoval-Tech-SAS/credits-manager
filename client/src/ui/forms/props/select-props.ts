import type { SelectOption } from '../types/select-option';

export type SelectProps = {
    options: SelectOption[];
    placeholder?: string;
    onChange: (value: string) => void;
    className?: string;
    defaultValue?: string;
    /** Permite volver a seleccionar el placeholder (útil en filtros donde "sin selección" es un valor válido). */
    placeholderSelectable?: boolean;
};
