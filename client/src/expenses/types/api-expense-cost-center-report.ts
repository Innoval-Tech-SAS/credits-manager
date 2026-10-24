/** Un renglón del reporte: un mes y un centro de costo (`null` si el gasto es anterior a la obligatoriedad). */
export type ApiExpenseCostCenterRow = {
    mes: string;
    centro_costo: { id: number; codigo: string; nombre: string } | null;
    cantidad: number;
    valor_bruto: string;
    valor_neto: string;
};

/** Respuesta de `GET /gastos/reporte-centro-costo`. */
export type ApiExpenseCostCenterReport = {
    filas: ApiExpenseCostCenterRow[];
    total_cantidad: number;
    total_bruto: string;
    total_neto: string;
};
