import type { FC } from 'react';

import { LineChart } from '@ui/charts/components/line-chart';
import type { LineChartSeries } from '@ui/charts/props/line-chart-props';
import { DASHBOARD_CHART_COLORS } from '../constants/dashboard-chart-colors';
import type { LoginTrendChartCardProps } from '../props/login-trend-chart-card-props';

const FORMATO_DIA = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: 'short', timeZone: 'America/Bogota' });

function etiquetaDeDia(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-').map(Number);

    return FORMATO_DIA.format(new Date(anio, mes - 1, dia));
}

/** Tendencia de accesos a la plataforma: único gráfico propio del home, no duplica el de Salud del sistema. */
export const LoginTrendChartCard: FC<LoginTrendChartCardProps> = ({ tendencia }) => {
    const series: LineChartSeries[] = [
        { name: 'Exitosos', data: tendencia.map((punto) => punto.exitosos), color: DASHBOARD_CHART_COLORS.loginsExitosos },
        { name: 'Fallidos', data: tendencia.map((punto) => punto.fallidos), color: DASHBOARD_CHART_COLORS.loginsFallidos },
    ];

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-6">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Accesos a la plataforma</h2>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Últimos 14 días, todas las empresas.</p>
            </div>

            <LineChart categories={tendencia.map((punto) => etiquetaDeDia(punto.fecha))} series={series} height={280} />
        </section>
    );
};
