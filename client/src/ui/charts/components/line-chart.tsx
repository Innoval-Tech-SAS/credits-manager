import type { FC } from 'react';
import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

import type { LineChartProps } from '../props/line-chart-props';

export const LineChart: FC<LineChartProps> = ({ categories, series, height = 310, valueFormatter }) => {
    const formatear = valueFormatter ?? ((value: number) => value.toLocaleString('es-CO'));

    const options: ApexOptions = {
        chart: { type: 'line', fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
        colors: series.map((serie) => serie.color),
        stroke: { curve: 'straight', width: 2 },
        markers: { size: 0, strokeWidth: 2, strokeColors: '#fff', hover: { size: 5 } },
        dataLabels: { enabled: false },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { formatter: formatear } },
        grid: { yaxis: { lines: { show: true } } },
        legend: { position: 'top', horizontalAlign: 'right', fontFamily: 'Outfit, sans-serif', markers: { size: 6 } },
        tooltip: { y: { formatter: formatear } },
    };

    return <Chart type="line" height={height} series={series.map(({ name, data }) => ({ name, data }))} options={options} />;
};
