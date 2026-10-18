/** Raw shape of a row returned by `GET /contabilidad/comprobantes/documentos-cruce` (saldos vivos del tercero en la cuenta). */
export type ApiCrossDocument = {
    id: number;
    naturaleza: 'por_cobrar' | 'por_pagar' | 'anticipo_proveedor' | 'anticipo_cliente';
    tipo_documento: string;
    numero_documento: string;
    fecha: string;
    fecha_vencimiento: string | null;
    concepto: string;
    valor_original: string;
    saldo: string;
    estado: 'pendiente' | 'abonado_parcial';
};
