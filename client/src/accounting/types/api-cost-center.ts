/** Raw shape of a row inside `GET /contabilidad/centros-costo`'s paginated `data`, and of `POST`/`PATCH`. */
export type ApiCostCenter = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
