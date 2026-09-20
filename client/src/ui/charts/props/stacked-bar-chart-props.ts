export type StackedBarChartSeries = {
    name: string;
    data: number[];
    color: string;
};

export type StackedBarChartProps = {
    categories: string[];
    series: StackedBarChartSeries[];
    height?: number;
    /** Formatea el valor en el eje Y y en el tooltip (ej. `formatMoney`). */
    valueFormatter?: (value: number) => string;
};
