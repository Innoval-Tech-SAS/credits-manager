/** Raw shape of a row returned by `GET /cartera-terceros` (CxP o CxC, paginado). */
export type ApiCarteraDocument = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    fecha: string;
    fecha_vencimiento: string | null;
    dias_mora: number;
    dias_por_vencer: number | null;
    concepto: string;
    valor_original: string;
    abonado: string;
    saldo: string;
    estado: 'pendiente' | 'abonado_parcial' | 'cancelado' | 'vencido' | 'anulado';
    tercero: { id: number; nombre: string; tipo_documento: string; numero_documento: string };
    plan_cuenta: { id: number; codigo: string; nombre: string } | null;
    modulo_origen: string | null;
    origen_id: number | null;
};

/** Raw shape of `GET /cartera-terceros/edades`. */
export type ApiCarteraEdades = {
    corriente: string;
    '1_30': string;
    '31_60': string;
    '61_90': string;
    mas_90: string;
};
