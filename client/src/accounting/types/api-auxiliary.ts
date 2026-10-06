/** Un movimiento del auxiliar con su saldo corrido (`GET /contabilidad/auxiliares`). */
export type ApiAuxiliaryMovement = {
    fecha: string;
    comprobante: string;
    descripcion: string | null;
    tercero: string | null;
    debito: string;
    credito: string;
    saldo: string;
};

/** Una cuenta del auxiliar: saldo inicial, movimientos del rango y saldo final. */
export type ApiAuxiliaryGroup = {
    cuenta: { id: number; codigo: string; nombre: string; naturaleza: 'debito' | 'credito' };
    saldo_inicial: string;
    total_debito: string;
    total_credito: string;
    saldo_final: string;
    movimientos: ApiAuxiliaryMovement[];
};

/** Respuesta de `GET /contabilidad/auxiliares`. */
export type ApiAuxiliary = {
    grupos: ApiAuxiliaryGroup[];
    total_debito: string;
    total_credito: string;
};
