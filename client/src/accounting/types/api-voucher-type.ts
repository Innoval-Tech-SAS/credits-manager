/** Raw shape of a row inside `GET /contabilidad/tipos-comprobante`'s paginated `data`, and of `POST`. */
export type ApiVoucherType = {
    id: number;
    codigo: string;
    nombre: string;
    consecutivo_actual: number;
};
