/** Marca vigente (conciliado o puente) de un movimiento ya resuelto. */
export type ApiMarcaConciliacion = {
    id: number;
    movimiento_extracto_id: number;
    asiento_contable_id: number | null;
    comprobante_generado_id: number | null;
    tipo: 'conciliado' | 'puente' | 'reversa';
    valor: string;
    observacion: string | null;
};

/** Fila del lado "extracto" de `GET /conciliacion/extractos/{id}/candidatos`. */
export type ApiExtractoMovimiento = {
    id: number;
    fecha: string;
    descripcion: string;
    documento: string | null;
    valor: string;
    estado: 'pendiente' | 'conciliado' | 'puente';
    marca_vigente: ApiMarcaConciliacion | null;
};

/** Fila del lado "libro" (asientos candidatos de la cuenta PUC del banco). */
export type ApiAsientoConciliable = {
    id: number;
    comprobante_id: number;
    fecha: string;
    consecutivo: number;
    tipo_comprobante: string;
    modulo_origen: string | null;
    origen_id: number | null;
    descripcion: string | null;
    debito: string;
    credito: string;
};

export type ApiSugerenciaConciliacion = {
    movimiento_extracto_id: number;
    sugeridos: number[];
    estado_sugerido: 'exacto' | 'duplicado' | 'con_diferencia' | 'sin_candidato';
};

/** Raw shape of `GET /conciliacion/extractos/{id}/candidatos`. */
export type ApiCandidatosConciliacion = {
    extracto: {
        id: number;
        cuenta_bancaria_id: number;
        fecha_desde: string;
        fecha_hasta: string;
        saldo_anterior: string;
        saldo_actual: string;
        estado: 'cargando' | 'cargado' | 'rechazado' | 'cerrado';
    };
    movimientos: ApiExtractoMovimiento[];
    asientos: ApiAsientoConciliable[];
    sugerencias: ApiSugerenciaConciliacion[];
};
