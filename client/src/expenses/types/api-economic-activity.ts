/** Raw shape of a row returned by `GET /gastos/actividades-economicas`, `POST /gastos/actividades-economicas` and `PATCH /gastos/actividades-economicas/{id}`. */
export type ApiEconomicActivity = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
