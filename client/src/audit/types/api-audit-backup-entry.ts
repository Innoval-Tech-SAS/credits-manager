export type AuditBackupReason = 'redis_no_disponible' | 'evento_invalido' | 'payload_invalido' | 'error_insercion';

export type AuditBackupStatus = 'pendiente' | 'recuperado' | 'no_recuperable';

/** Raw shape returned by the rows of `GET /auditoria/respaldo`. */
export type ApiAuditBackupEntry = {
    evento_id: string | null;
    registrado_en: string;
    motivo_fallo: AuditBackupReason;
    error: string;
    estado: AuditBackupStatus;
    descripcion: string | null;
    modulo: string | null;
    accion: string | null;
};

/** Raw shape returned by `POST /auditoria/respaldo/recuperar`. */
export type ApiAuditRecoveryResult = {
    recuperados: number;
    fallidos: number;
};
