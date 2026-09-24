import type { MouseEventHandler, ReactNode } from 'react';

export type ButtonProps = {
    /** También es el nombre accesible y el tooltip cuando el botón se muestra solo con icono. */
    children: ReactNode;
    type?: 'button' | 'submit' | 'reset';
    size?: 'sm' | 'md';
    variant?: 'primary' | 'outline';
    startIcon?: ReactNode;
    endIcon?: ReactNode;
    /** Solo el icono, cuadrado; el texto pasa al tooltip. Requiere `startIcon` o `endIcon`. */
    iconOnly?: boolean;
    /** Solo el icono por debajo de ese ancho y icono con texto desde él. Para el botón principal de un listado. */
    collapseBelow?: 'md';
    /** Tooltip y nombre accesible si `children` no es un texto simple. */
    label?: string;
    onClick?: MouseEventHandler<HTMLButtonElement>;
    disabled?: boolean;
    className?: string;
};
