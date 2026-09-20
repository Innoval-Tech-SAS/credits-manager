import type { FC } from 'react';
import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

import type { DonutChartProps } from '../props/donut-chart-props';

export const DonutChart: FC<DonutChartProps> = ({
    series,
    labels,
    colors,
    totalLabel,
    totalValue,
    valueFormatter = (value) => value.toLocaleString('es-CO'),
    height = 260,
}) => {
    const options: ApexOptions = {
        chart: { type: 'donut', fontFamily: 'Outfit, sans-serif' },
        labels,
        colors,
        stroke: { show: false },
        dataLabels: { enabled: false },
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } },
        },
        tooltip: {
            y: { formatter: (value) => valueFormatter(value) },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontFamily: 'Outfit, sans-serif',
            markers: { size: 6 },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '13px' },
                        value: { show: true, fontSize: '20px', fontWeight: 700, formatter: (value) => valueFormatter(Number(value)) },
                        total: { show: true, label: totalLabel, formatter: () => totalValue },
                    },
                },
            },
        },
    };

    return (
        <div id="chartDarkStyle">
            <Chart type="donut" height={height} series={series} options={options} />
        </div>
    );
};
