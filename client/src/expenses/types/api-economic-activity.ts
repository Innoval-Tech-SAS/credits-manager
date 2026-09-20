/** Raw shape of a row returned by `GET /gastos/actividades-economicas` and `POST /gastos/actividades-economicas`. */
export type ApiEconomicActivity = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
