export type ToggleSwitchInputProps = {
    label?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    id?: string;
    className?: string;
};
