/** Usuario para poblar el filtro "Autor" de la bitácora, venga de `/usuarios` (empresa) o `/auditoria/usuarios` (superadmin). */
export type AuditUserOption = {
    id: number;
    nombre: string;
    usuario: string;
    /** Solo viene de la fuente cruzada (superadmin): distingue usuarios con el mismo nombre en empresas distintas. */
    empresa?: string;
};
