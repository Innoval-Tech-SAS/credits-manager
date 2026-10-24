/** Raw shape of a row returned by `GET /geografia/estados`. */
export type ApiState = {
    id: number;
    pais_id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
};
