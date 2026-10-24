/** Raw shape of a row returned by `GET /geografia/municipios`, `POST /geografia/municipios` and `PATCH /geografia/municipios/{id}`. */
export type ApiMunicipality = {
    id: number;
    estado_id: number;
    codigo: string;
    nombre: string;
    es_capital: boolean;
    activo: boolean;
    estado?: { id: number; pais_id: number; nombre: string };
};
