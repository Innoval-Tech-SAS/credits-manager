/** Raw shape of `asesor` nested in a client (`GET /clientes`, `GET /clientes/{id}`, `GET /clientes/{id}/resumen`). */
export type ApiAdvisorRef = {
    id: number;
    nombre: string;
};

/** Raw shape of a row in `GET /clientes/asesores-asignables`. */
export type ApiAdvisor = ApiAdvisorRef & {
    usuario: string;
    rol: { id: number; nombre: string } | null;
};
