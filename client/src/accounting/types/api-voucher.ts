/** Raw shape of one line, nested in `ApiVoucher.lineas` (`GET .../comprobantes/{id}`). */
export type ApiVoucherLine = {
    id: number;
    cuenta_id: number;
    cuenta?: { id: number; codigo: string; nombre: string };
    tercero_id: number | null;
    centro_costo_id: number | null;
    debito: string;
    credito: string;
    descripcion: string | null;
    orden: number;
};

/** Raw shape returned by `GET /contabilidad/comprobantes` (rows), `POST`, `GET .../{id}` and `.../reversar`. */
export type ApiVoucher = {
    id: number;
    tipo_comprobante_id: number;
    tipo_comprobante?: { id: number; codigo: string; nombre: string };
    consecutivo: number;
    periodo_id: number;
    fecha: string;
    tercero_id: number | null;
    tercero?: { id: number; nombre: string } | null;
    concepto: string | null;
    modulo_origen: string | null;
    origen_id: number | null;
    documento_soporte: string | null;
    estado: 'borrador' | 'contabilizado' | 'anulado';
    total_debito: string;
    total_credito: string;
    comprobante_reverso_id: number | null;
    lineas?: ApiVoucherLine[];
};
