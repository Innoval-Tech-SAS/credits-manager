/** Raw shape returned by the rows of `GET /auditoria/usuarios` (solo superadmin). */
export type ApiAuditUser = {
    id: number;
    nombre: string;
    usuario: string;
    empresa: string;
};
