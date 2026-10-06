/** Una cuenta de la conciliación: lo cargado en la importación frente al balance de prueba a la misma fecha. */
export type ApiOpeningReconciliationRow = {
    codigo: string;
    nombre: string;
    cargado_debito: string;
    cargado_credito: string;
    balance_debito: string;
    balance_credito: string;
    diferencia: string;
};

/** Respuesta de `GET /contabilidad/conciliacion-apertura`. */
export type ApiOpeningReconciliation = {
    filas: ApiOpeningReconciliationRow[];
    total_cargado_debito: string;
    total_cargado_credito: string;
    total_balance_debito: string;
    total_balance_credito: string;
    cuentas_con_diferencia: number;
    cuadra: boolean;
};
