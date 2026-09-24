/** Raw shape of a row returned by `GET /geografia/paises`. */
export type ApiCountry = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
