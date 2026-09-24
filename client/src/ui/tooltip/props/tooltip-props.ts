import type { ReactElement } from 'react';

export type TooltipProps = {
    label: string;
    /** No muestra el tooltip desde este ancho (p. ej. cuando el texto ya es visible a partir de `md`). */
    hideFrom?: 'md';
    /** Un solo elemento (botón, enlace o `span`) con su propio `aria-label`. */
    children: ReactElement;
};
