import type { ApiPortfolioSummary } from '../types/api-portfolio-summary';

export type PortfolioSummaryCardsProps = {
    totales: ApiPortfolioSummary['totales'];
    envejecimientoMora: ApiPortfolioSummary['envejecimiento_mora'];
};
