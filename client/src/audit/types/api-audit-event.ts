import type { AuditAction } from './audit-action';

/** Raw shape returned by the rows of `GET /auditoria/bitacora`. */
export type ApiAuditEvent = {
    id: number;
    evento_id: string;
    empresa_id: number | null;
    usuario_id: number | null;
    autor_nombre: string | null;
    autor_usuario: string | null;
    autor_rol: string | null;
    es_superadmin: boolean;
    modulo: string;
    accion: AuditAction;
    descripcion: string;
    motivo: string | null;
    entidad: string | null;
    entidad_id: number | null;
    valores_anteriores: Record<string, unknown> | null;
    valores_nuevos: Record<string, unknown> | null;
    ip: string | null;
    fecha_hora: string;
    creado_en: string;
};
