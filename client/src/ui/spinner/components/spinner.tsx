import type { FC } from 'react';

import { SPINNER_COLOR_CLASSES } from '../constants/spinner-color-classes';
import { SPINNER_SIZE_CLASSES } from '../constants/spinner-size-classes';
import type { SpinnerProps } from '../props/spinner-props';

export const Spinner: FC<SpinnerProps> = ({ size = 'md', color = 'brand', className = '' }) => (
    <span
        role="status"
        aria-label="Cargando"
        className={`inline-block animate-spin rounded-full ${SPINNER_SIZE_CLASSES[size]} ${SPINNER_COLOR_CLASSES[color]} ${className}`}
    />
);
