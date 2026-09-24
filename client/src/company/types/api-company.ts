/** Raw shape returned by `GET /empresa`, `GET /empresas` (rows), `GET /empresas/{id}`, `POST /empresas` and `PATCH /empresas/{id}`. */
export type ApiCompany = {
    id: number;
    nombre_empresa: string;
    nit: string | null;
    direccion: string | null;
    municipio_id: number | null;
    /** En el listado del superadmin solo trae `id` y `nombre`; el resto llega en el detalle y en la empresa propia. */
    municipio: { id: number; nombre: string; estado?: { id: number; pais_id: number; nombre: string } } | null;
    telefono: string | null;
    correo: string | null;
    logo_path: string | null;
    creado_en: string | null;
};
