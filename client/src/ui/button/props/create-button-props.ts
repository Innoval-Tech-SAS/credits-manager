import type { MouseEventHandler, ReactNode } from 'react';

export type CreateButtonProps = {
    /** Sustantivo del recurso a crear, ej. "cliente", "nota crédito". Se antepone siempre con "Crear ". */
    label: string;
    icon: ReactNode;
    onClick: MouseEventHandler<HTMLButtonElement>;
    disabled?: boolean;
};
