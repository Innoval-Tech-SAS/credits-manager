import type { ApiPortfolioEntry } from '../types/api-portfolio-summary';

export type PortfolioCurrentTableProps = {
    entradas: ApiPortfolioEntry[];
    onView: (creditoId: number) => void;
};
