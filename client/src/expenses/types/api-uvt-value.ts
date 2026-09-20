/** Raw shape of a row returned by `GET /gastos/valores-uvt` and `POST /gastos/valores-uvt`. */
export type ApiUvtValue = {
    id: number;
    anio: number;
    valor: string;
};
