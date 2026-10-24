import type { ApiPortfolioEntry } from '../types/api-portfolio-summary';

export type PortfolioOverdueTableProps = {
    entradas: ApiPortfolioEntry[];
    onView: (creditoId: number) => void;
    onOpenEmployerReport: () => void;
};
