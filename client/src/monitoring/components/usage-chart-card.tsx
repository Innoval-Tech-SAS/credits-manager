import type { FC } from 'react';

import { LineChart } from '@ui/charts/components/line-chart';
import { USAGE_CHART_COLORS } from '../constants/live-status';
import type { UsageChartCardProps } from '../props/usage-chart-card-props';
import { formatPercentage } from '../utils/format-percentage';

export const UsageChartCard: FC<UsageChartCardProps> = ({ history }) => (
    <section className="h-full overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 dark:border-gray-800 dark:bg-white/3 sm:px-6 sm:pt-6">
        <div className="mb-6">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Uso del servidor</h2>
            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Últimos 5 minutos, desde que abriste esta página.</p>
        </div>

        {history.length < 2 ? (
            <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Reuniendo muestras…</p>
        ) : (
            <LineChart
                categories={history.map((sample) => sample.hora)}
                series={[
                    { name: 'CPU', data: history.map((sample) => sample.cpu), color: USAGE_CHART_COLORS.cpu },
                    { name: 'Memoria', data: history.map((sample) => sample.memoria), color: USAGE_CHART_COLORS.memoria },
                ]}
                height={280}
                valueFormatter={(value) => formatPercentage(value)}
            />
        )}
    </section>
);
