/** Un prefijo de cuenta (CxC o CxP): total del auxiliar contra el total del balance de prueba. */
export type ApiClosingControlsBalance = {
    total_auxiliar: string;
    total_balance: string;
    diferencia: string;
    cuadra: boolean;
};

export type ApiClosingControlsContraryBalanceRow = {
    codigo: string;
    nombre: string;
    saldo: string;
};

export type ApiClosingControlsInconsistentDocumentRow = {
    id: number;
    numero_documento: string;
    concepto: string;
    estado: string;
    saldo: string;
};

export type ApiClosingControlsUnreconciledEntryRow = {
    id: number;
    comprobante_id: number;
    fecha: string;
    modulo_origen: string;
    origen_id: number;
    cuenta_codigo: string;
    debito: string;
    credito: string;
};

/** Respuesta de `GET /contabilidad/controles-cierre` (6.6.3). */
export type ApiClosingControls = {
    cuentas_por_cobrar: ApiClosingControlsBalance;
    cuentas_por_pagar: ApiClosingControlsBalance;
    saldos_contrarios: { filas: ApiClosingControlsContraryBalanceRow[]; contador: number };
    documentos_inconsistentes: { filas: ApiClosingControlsInconsistentDocumentRow[]; contador: number };
    conciliacion_bancaria: { filas: ApiClosingControlsUnreconciledEntryRow[]; contador: number };
};
