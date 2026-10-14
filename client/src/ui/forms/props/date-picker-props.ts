export type DatePickerProps = {
    id: string;
    name?: string;
    mode?: 'single' | 'range' | 'month';
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
    /** `md` (por defecto) es el tamaño de siempre; `sm` achica alto y relleno para barras de filtros densas. */
    size?: 'sm' | 'md';
};
