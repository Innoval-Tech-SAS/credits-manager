/** Raw shape returned by `GET /terceros` (rows), `POST /terceros` and `PATCH /terceros/{id}` — también anidado como `cliente.tercero` y `proveedor.tercero`. */
export type ApiThirdParty = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    nombre: string;
    direccion: string | null;
    barrio: string | null;
    ciudad: string | null;
    celular: string | null;
    telefono_fijo: string | null;
    correo: string | null;
    regimen_tributario: string | null;
    tipo_persona: 'natural' | 'juridica' | null;
    autorretenedor: boolean;
    responsable_iva: boolean;
    actividad_economica_id: number | null;
    activo: boolean;
    /** A qué extensión pertenece este tercero — null si aún no tiene ninguna (recién creado desde el catálogo genérico). */
    tipo: 'cliente' | 'proveedor' | 'otro' | null;
    /** Solo presente en la respuesta de `POST /terceros`: si el tercero ya existía (por documento) y se reutilizó. */
    tercero_reutilizado?: boolean;
};
