export type CheckboxProps = {
    label?: string;
    checked: boolean;
    className?: string;
    id?: string;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
};
