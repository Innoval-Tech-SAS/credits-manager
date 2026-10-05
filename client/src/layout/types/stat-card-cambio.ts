/** Comparación que muestra la insignia de una `StatCard`. */
export type StatCardCambio = {
    /** Porcentaje de variación; `null` si no hay base para comparar. */
    porcentaje: number | null;
    /** Define el color de la insignia: una subida de gastos es desfavorable, una de recaudo es favorable. */
    favorable: boolean;
    /** Texto bajo la cifra, ej. "vs. mes anterior · año anterior +12,0 %". */
    referencia: string;
};
