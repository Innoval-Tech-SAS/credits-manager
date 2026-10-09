/** Raw shape of `comportamiento_mensual` in `GET /clientes/{id}/resumen`. Montos como cadenas decimales. */
export type ApiClientMonthlyBehaviorMonth = {
    /** `YYYY-MM`, hora de Bogotá. */
    mes: string;
    recaudo: string;
    saldo_cierre: string;
    cuotas_a_tiempo: number;
    cuotas_con_mora: number;
};

export type ApiClientMonthlyBehavior = {
    meses: ApiClientMonthlyBehaviorMonth[];
    totales: {
        recaudo: string;
        cuotas_a_tiempo: number;
        cuotas_con_mora: number;
    };
};
