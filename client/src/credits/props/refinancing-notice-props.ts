import type { ApiRefinancingLink } from '../types/api-refinancing-link';

export type RefinancingNoticeProps = {
    vinculo: ApiRefinancingLink;
    /** Decimal string del valor del crédito que se está mostrando. */
    valorPrestamo: string;
    /** Sufijo de la ruta del crédito relacionado: '' para el detalle, '/estado-cuenta' para el estado de cuenta. */
    linkSuffix?: string;
    className?: string;
};
