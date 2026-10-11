import type { MouseEventHandler, ReactNode } from 'react';

export type ActionButtonVariant = 'default' | 'danger' | 'warning';

export type ActionButtonProps = {
    icon: ReactNode;
    /** Nombre accesible y texto del tooltip. */
    label: string;
    onClick: MouseEventHandler<HTMLButtonElement>;
    variant?: ActionButtonVariant;
    disabled?: boolean;
};
