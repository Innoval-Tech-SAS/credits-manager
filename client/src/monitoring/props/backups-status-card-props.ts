import type { ApiBackupsStatus } from '../types/api-backups-status';

export type BackupsStatusCardProps = {
    status: ApiBackupsStatus | null;
    error: string | null;
};
