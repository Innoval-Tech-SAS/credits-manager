import type { FC } from 'react';
import { useState } from 'react';

import { ChartTabs } from '@ui/charts/components/chart-tabs';
import { LineChart } from '@ui/charts/components/line-chart';
import { USAGE_CHART_COLORS } from '../constants/live-status';
import type { UsageChartCardProps } from '../props/usage-chart-card-props';
import { calculateNetworkRates } from '../utils/calculate-network-rates';
import { formatBytes } from '../utils/format-bytes';
import { formatPercentage } from '../utils/format-percentage';

type Metrica = 'cpu' | 'memoria' | 'red';

const OPCIONES_METRICA = [
    { value: 'cpu', label: 'CPU' },
    { value: 'memoria', label: 'Memoria' },
    { value: 'red', label: 'Red' },
];

export const UsageChartCard: FC<UsageChartCardProps> = ({ history }) => {
    const [metrica, setMetrica] = useState<Metrica>('cpu');

    const categories = history.map((sample) => sample.hora);

    const renderGrafico = () => {
        if (metrica === 'red') {
            const { recepcion, envio } = calculateNetworkRates(history);

            return (
                <LineChart
                    categories={categories}
                    series={[
                        { name: 'Recepción', data: recepcion, color: USAGE_CHART_COLORS.redRecepcion },
                        { name: 'Envío', data: envio, color: USAGE_CHART_COLORS.redEnvio },
                    ]}
                    height={280}
                    valueFormatter={(valor) => `${formatBytes(valor)}/s`}
                />
            );
        }

        const serie =
            metrica === 'cpu'
                ? { name: 'CPU', data: history.map((sample) => sample.cpu), color: USAGE_CHART_COLORS.cpu }
                : { name: 'Memoria', data: history.map((sample) => sample.memoria), color: USAGE_CHART_COLORS.memoria };

        return <LineChart categories={categories} series={[serie]} height={280} valueFormatter={(valor) => formatPercentage(valor)} />;
    };

    return (
        <section className="h-full overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 dark:border-gray-800 dark:bg-white/3 sm:px-6 sm:pt-6">
            <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Uso del servidor</h2>
                    <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Últimos 5 minutos, desde que abriste esta página.</p>
                </div>

                <div className="flex items-start sm:justify-end">
                    <ChartTabs options={OPCIONES_METRICA} value={metrica} onChange={(valor) => setMetrica(valor as Metrica)} />
                </div>
            </div>

            {history.length < 2 ? <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Reuniendo muestras…</p> : renderGrafico()}
        </section>
    );
};
