import type { FC } from 'react';
import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

import type { StackedBarChartProps } from '../props/stacked-bar-chart-props';

export const StackedBarChart: FC<StackedBarChartProps> = ({ categories, series, height = 300, valueFormatter }) => {
    const formatear = valueFormatter ?? ((value: number) => value.toLocaleString('es-CO'));

    const options: ApexOptions = {
        chart: { type: 'bar', fontFamily: 'Outfit, sans-serif', stacked: true, toolbar: { show: false } },
        colors: series.map((serie) => serie.color),
        plotOptions: { bar: { borderRadius: 3, borderRadiusApplication: 'end', columnWidth: '55%' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
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

    return <Chart type="bar" height={height} series={series.map(({ name, data }) => ({ name, data }))} options={options} />;
};
