import type { SelectOption } from '@ui/forms/types/select-option';

export type DashboardFilterSelectProps = {
    options: SelectOption[];
    /** `''` cuando no hay selección: muestra el placeholder. */
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
};
