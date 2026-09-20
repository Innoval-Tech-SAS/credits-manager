import type { ApiCompany } from '../../company/types/api-company';
import type { ApiAuditEvent } from '../types/api-audit-event';

export type AuditEventDetailModalProps = {
    event: ApiAuditEvent | null;
    companies: ApiCompany[] | null;
    onClose: () => void;
};
