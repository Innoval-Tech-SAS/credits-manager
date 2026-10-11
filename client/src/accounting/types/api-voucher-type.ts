/** Raw shape of a row inside `GET /contabilidad/tipos-comprobante`'s paginated `data`, and of `POST`/`GET {id}`/`PATCH`. */
export type ApiVoucherType = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
    consecutivo_actual: number;
};
