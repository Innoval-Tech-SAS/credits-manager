import type { BackupClass } from '../types/api-backups-status';

export const BACKUP_CLASS_LABELS: Record<BackupClass, string> = {
    diario: 'Diarios',
    semanal: 'Semanales',
    mensual: 'Mensuales',
    manual: 'Manuales',
};
