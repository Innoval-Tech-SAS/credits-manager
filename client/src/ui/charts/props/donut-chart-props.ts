export type DonutChartProps = {
    series: number[];
    labels: string[];
    colors: string[];
    /** Texto del centro de la dona cuando no hay slice bajo el cursor. */
    totalLabel: string;
    /** Valor ya formateado a mostrar en el centro (ej. "$1.234.567" o "12"). */
    totalValue: string;
    /** Formatea el valor de un slice individual al pasar el cursor sobre él. Por defecto usa separador de miles. */
    valueFormatter?: (value: number) => string;
    height?: number;
};
