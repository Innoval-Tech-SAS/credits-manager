/** Raw shape of a row returned by `GET /gastos/tipos-gasto`, `GET {id}`, `POST` and `PATCH`. */
export type ApiExpenseType = {
    id: number;
    codigo: string;
    nombre: string;
    requiere_tercero: boolean;
    aplica_iva: boolean;
    activo: boolean;
    /** Solo viene en `GET {id}`: deshabilita la eliminación en el frontend antes de chocar con un 422. */
    tiene_gastos_asociados?: boolean;
};
