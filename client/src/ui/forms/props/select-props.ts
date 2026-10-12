import type { SelectOption } from '../types/select-option';

export type SelectProps = {
    id?: string;
    name?: string;
    /** Nombre accesible cuando no hay un `<Label>` visible emparejado (ej. filtros compactos de una toolbar). */
    ariaLabel?: string;
    options: SelectOption[];
    placeholder?: string;
    onChange: (value: string) => void;
    className?: string;
    defaultValue?: string;
    /** Permite volver a seleccionar el placeholder (útil en filtros donde "sin selección" es un valor válido). */
    placeholderSelectable?: boolean;
};
