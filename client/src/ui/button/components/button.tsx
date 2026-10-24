import type { FC } from 'react';

import { Tooltip } from '@ui/tooltip/components/tooltip';
import type { ButtonProps } from '../props/button-props';
import { BUTTON_ICON_ONLY_CLASSES, BUTTON_SIZE_CLASSES, BUTTON_VARIANT_CLASSES } from '../constants/button-styles';

export const Button: FC<ButtonProps> = ({
    children,
    type = 'button',
    size = 'md',
    variant = 'primary',
    startIcon,
    endIcon,
    iconOnly = false,
    collapseBelow,
    label,
    onClick,
    className = '',
    disabled = false,
}) => {
    const hasIcon = Boolean(startIcon || endIcon);
    const isIconOnly = iconOnly && hasIcon;
    const collapses = !isIconOnly && Boolean(collapseBelow) && hasIcon;
    const isCompact = isIconOnly || collapses;
    const text = label ?? (typeof children === 'string' ? children : undefined);

    const sizeClasses = isIconOnly ? BUTTON_ICON_ONLY_CLASSES.always : collapses ? BUTTON_ICON_ONLY_CLASSES.belowMd : BUTTON_SIZE_CLASSES[size];

    // `relative` contiene el texto `sr-only` (absoluto): sin un ancestro posicionado se salía del contenedor con scroll y alargaba el documento.
    const button = (
        <button
            type={type}
            aria-label={isCompact ? text : undefined}
            className={`${isCompact ? 'relative ' : ''}inline-flex items-center justify-center gap-2 rounded-lg transition ${className} ${sizeClasses} ${
                BUTTON_VARIANT_CLASSES[variant]
            } ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
            onClick={onClick}
            disabled={disabled}
        >
            {startIcon && <span className="flex items-center">{startIcon}</span>}
            {isCompact ? <span className={isIconOnly ? 'sr-only' : 'sr-only md:not-sr-only'}>{children}</span> : children}
            {endIcon && <span className="flex items-center">{endIcon}</span>}
        </button>
    );

    if (!isCompact || !text) {
        return button;
    }

    return (
        <Tooltip label={text} hideFrom={collapses ? collapseBelow : undefined}>
            {button}
        </Tooltip>
    );
};
