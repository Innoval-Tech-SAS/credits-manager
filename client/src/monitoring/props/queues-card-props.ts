import type { ApiSystemHealth } from '../types/api-system-health';

export type QueuesCardProps = {
    colas: ApiSystemHealth['colas'];
    auditoria: ApiSystemHealth['auditoria'];
};
