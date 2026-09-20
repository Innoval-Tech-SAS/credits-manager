import type { FC } from 'react';

import { useAuth } from '../../auth/hooks/use-auth';
import type { ApiCompany } from '../../company/types/api-company';
import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { AuditLogView } from '../components/audit-log-view';

/** El superadmin carga las empresas para el filtro y la columna de empresa; el resto no las necesita. */
const SuperAdminAuditLog: FC = () => {
    const { data } = useResource<PaginatedResponse<ApiCompany>>('/empresas?per_page=100');

    return <AuditLogView companies={data?.data ?? []} />;
};

export const AuditLogPage: FC = () => {
    const { user } = useAuth();

    return user?.isSuperAdmin ? <SuperAdminAuditLog /> : <AuditLogView companies={null} />;
};
