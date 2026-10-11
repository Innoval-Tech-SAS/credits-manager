/** Raw shape returned by `GET /empleadores` (rows) and `POST /empleadores`. */
export type ApiEmployer = {
    id: number;
    nombre: string;
    nit: string | null;
    digito_verificacion: string | null;
    direccion: string | null;
    telefono: string | null;
};
