import type { FC } from 'react';

import { Tooltip } from '@ui/tooltip/components/tooltip';
import type { ActionButtonProps } from '../props/action-button-props';
import { ACTION_BUTTON_VARIANT_CLASSES } from '../constants/action-button-styles';

/** Botón de acción de solo icono para columnas "Acciones" de `DataTable`; el texto va al tooltip y al nombre accesible. */
export const ActionButton: FC<ActionButtonProps> = ({ icon, label, onClick, variant = 'default', disabled = false }) => (
    <Tooltip label={label}>
        <button
            type="button"
            aria-label={label}
            onClick={onClick}
            disabled={disabled}
            className={`${ACTION_BUTTON_VARIANT_CLASSES[variant]} disabled:cursor-not-allowed disabled:opacity-40`}
        >
            {icon}
        </button>
    </Tooltip>
);
