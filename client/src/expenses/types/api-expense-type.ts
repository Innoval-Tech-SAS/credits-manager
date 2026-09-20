/** Raw shape of a row returned by `GET /gastos/tipos-gasto` and `POST /gastos/tipos-gasto`. */
export type ApiExpenseType = {
    id: number;
    codigo: string;
    nombre: string;
    requiere_tercero: boolean;
    aplica_iva: boolean;
    activo: boolean;
};
