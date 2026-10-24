import type { AuditBackupReason, AuditBackupStatus } from '../types/api-audit-backup-entry';

export const AUDIT_BACKUP_REASON_LABELS: Record<AuditBackupReason, string> = {
    redis_no_disponible: 'Servicio de auditoría no disponible',
    evento_invalido: 'Evento inválido',
    payload_invalido: 'Mensaje inválido',
    error_insercion: 'Error al insertar',
};

export const AUDIT_BACKUP_STATUS_LABELS: Record<AuditBackupStatus, string> = {
    pendiente: 'Pendiente',
    recuperado: 'Recuperado',
    no_recuperable: 'No recuperable',
};

export const AUDIT_BACKUP_STATUS_CLASSES: Record<AuditBackupStatus, string> = {
    pendiente: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400',
    recuperado: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    no_recuperable: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
};

export const AUDIT_BACKUP_STATUS_OPTIONS = [
    { value: 'pendiente', label: 'Pendientes' },
    { value: 'recuperado', label: 'Recuperados' },
    { value: 'no_recuperable', label: 'No recuperables' },
];
