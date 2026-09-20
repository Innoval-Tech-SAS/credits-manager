import type { FC } from 'react';

import type { ButtonProps } from '../props/button-props';
import { BUTTON_SIZE_CLASSES, BUTTON_VARIANT_CLASSES } from '../constants/button-styles';

export const Button: FC<ButtonProps> = ({
    children,
    type = 'button',
    size = 'md',
    variant = 'primary',
    startIcon,
    endIcon,
    onClick,
    className = '',
    disabled = false,
}) => {
    return (
        <button
            type={type}
            className={`inline-flex items-center justify-center gap-2 rounded-lg transition ${className} ${
                BUTTON_SIZE_CLASSES[size]
            } ${BUTTON_VARIANT_CLASSES[variant]} ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
            onClick={onClick}
            disabled={disabled}
        >
            {startIcon && <span className="flex items-center">{startIcon}</span>}
            {children}
            {endIcon && <span className="flex items-center">{endIcon}</span>}
        </button>
    );
};
