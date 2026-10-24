export type CheckboxProps = {
    label?: string;
    /** Nombre accesible cuando la casilla no lleva `label` visible (p. ej. "todos los permisos de Clientes"). */
    ariaLabel?: string;
    checked: boolean;
    /** Estado parcial (p. ej. "algunos de este grupo"): se muestra un guion y se anuncia como `aria-checked="mixed"`. */
    indeterminate?: boolean;
    className?: string;
    id?: string;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
};
