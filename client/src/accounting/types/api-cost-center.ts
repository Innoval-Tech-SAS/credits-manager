/** Raw shape of a row inside `GET /contabilidad/centros-costo`'s paginated `data`, and of `POST`/`GET {id}`/`PATCH`. */
export type ApiCostCenter = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
    /** Solo viene en `GET {id}`: deshabilita la eliminación en el frontend antes de chocar con un 422. */
    tiene_movimientos_asociados?: boolean;
};
