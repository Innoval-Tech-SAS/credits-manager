import type { FC } from 'react';

import { LineChart } from '@ui/charts/components/line-chart';
import type { LineChartSeries } from '@ui/charts/props/line-chart-props';
import { formatMoney } from '../../shared/utils/format-money';
import type { DashboardHistoricoCardProps } from '../props/dashboard-historico-card-props';
import { DASHBOARD_CHART_COLORS } from '../constants/dashboard-chart-colors';
import type { ApiDashboardSummary } from '../types/api-dashboard-summary';

const FORMATO_MES = new Intl.DateTimeFormat('es-CO', { month: 'short', year: '2-digit' });

function etiquetaDeMes(mes: string): string {
    const [anio, numeroMes] = mes.split('-').map(Number);

    return FORMATO_MES.format(new Date(anio, numeroMes - 1, 1));
}

function construirSeries(historico: ApiDashboardSummary['historico']): LineChartSeries[] {
    const series: LineChartSeries[] = [];

    if (historico.colocacion) {
        series.push({ name: 'Colocación', data: historico.colocacion.map(Number), color: DASHBOARD_CHART_COLORS.colocacion });
    }

    if (historico.recaudo) {
        series.push({ name: 'Recaudo', data: historico.recaudo.map(Number), color: DASHBOARD_CHART_COLORS.recaudo });
    }

    if (historico.gastos) {
        series.push({ name: 'Gastos', data: historico.gastos.map(Number), color: DASHBOARD_CHART_COLORS.gastos });
    }

    return series;
}

export const DashboardHistoricoCard: FC<DashboardHistoricoCardProps> = ({ historico }) => {
    const series = construirSeries(historico);

    return (
        <section className="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 dark:border-gray-800 dark:bg-white/3 sm:px-6 sm:pt-6">
            <div className="mb-6">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Histórico mensual</h2>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Últimos 12 meses hasta el período elegido.</p>
            </div>

            {series.length === 0 ? (
                <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">No tienes permisos para ver los históricos.</p>
            ) : (
                <LineChart
                    categories={historico.meses.map(etiquetaDeMes)}
                    series={series}
                    valueFormatter={(valor) => formatMoney(valor)}
                />
            )}
        </section>
    );
};
