/** Raw shape of an item returned by `GET /clientes/{id}/documentos` and `POST /clientes/{id}/documentos`. */
export type ApiClientDocument = {
    id: number;
    nombre_original: string;
    ruta: string;
    mime_type: string;
    tamano: number;
    creado_en: string | null;
};
