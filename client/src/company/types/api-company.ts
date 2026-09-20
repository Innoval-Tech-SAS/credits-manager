/** Raw shape returned by `GET /empresa`, `GET /empresas` (rows), `GET /empresas/{id}`, `POST /empresas` and `PATCH /empresas/{id}`. */
export type ApiCompany = {
    id: number;
    nombre_empresa: string;
    nit: string | null;
    direccion: string | null;
    telefono: string | null;
    correo: string | null;
    logo_path: string | null;
    creado_en: string | null;
};
