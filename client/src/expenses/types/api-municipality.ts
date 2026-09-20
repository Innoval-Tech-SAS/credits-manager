/** Raw shape of a row returned by `GET /gastos/municipios` and `POST /gastos/municipios`. */
export type ApiMunicipality = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
