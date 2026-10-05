import type { ApiDashboardPagoHoy } from '../types/api-dashboard-summary';

export type PagosDelDiaCardProps = {
    pagos: ApiDashboardPagoHoy[];
    puedeRegistrarPago: boolean;
};
