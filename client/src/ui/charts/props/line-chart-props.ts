export type LineChartSeries = {
    name: string;
    data: number[];
    color: string;
};

export type LineChartProps = {
    categories: string[];
    series: LineChartSeries[];
    height?: number;
    /** Formatea el valor en el eje Y y en el tooltip (ej. `formatMoney`). */
    valueFormatter?: (value: number) => string;
};
