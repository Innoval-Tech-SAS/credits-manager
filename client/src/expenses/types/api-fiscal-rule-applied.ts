/** Raw shape of an entry inside `ApiExpense.reglas_fiscales_aplicadas` — la trazabilidad de RF-05. */
export type ApiFiscalRuleApplied = {
    id: number;
    tipo_retencion: 'retefuente' | 'reteica' | 'reteiva';
    incluida: boolean;
    regla: string;
    tarifa_aplicada: string | null;
    detalle: string | null;
};
