/** Raw shape returned by the rows of `GET /seguridad/sesiones`. */
export type ApiActiveSession = {
    id: string;
    user_id: number;
    ip_address: string | null;
    user_agent: string | null;
    /** Epoch en segundos (columna `last_activity` de la tabla `sessions` de Laravel). */
    last_activity: number;
    usuario_nombre: string;
    usuario_login: string;
    es_superadmin: boolean;
    empresa_id: number | null;
    nombre_empresa: string | null;
};
