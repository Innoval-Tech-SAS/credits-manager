export type DatePickerProps = {
    id: string;
    name?: string;
    mode?: 'single' | 'range';
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
};
