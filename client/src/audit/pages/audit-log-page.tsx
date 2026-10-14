import type { FC } from 'react';

import { useAuth } from '../../auth/hooks/use-auth';
import type { ApiUserListItem } from '../../auth/types/api-user-list-item';
import type { ApiCompany } from '../../company/types/api-company';
import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { AuditLogView } from '../components/audit-log-view';
import type { ApiAuditUser } from '../types/api-audit-user';

/** El superadmin carga las empresas para el filtro y la columna de empresa, y el catálogo cruzado de usuarios. */
const SuperAdminAuditLog: FC = () => {
    const { data: companies } = useResource<PaginatedResponse<ApiCompany>>('/empresas?per_page=100');
    const { data: usersData } = useResource<{ usuarios: ApiAuditUser[] }>('/auditoria/usuarios');

    return <AuditLogView companies={companies?.data ?? []} users={usersData?.usuarios ?? []} />;
};

/** El admin de empresa reusa el catálogo de usuarios de su propia empresa (`GET /usuarios`, ya acotado por `EmpresaScope`). */
const CompanyAuditLog: FC = () => {
    const { data: usersData } = useResource<PaginatedResponse<ApiUserListItem>>('/usuarios?per_page=100');

    return <AuditLogView companies={null} users={usersData?.data ?? []} />;
};

export const AuditLogPage: FC = () => {
    const { user } = useAuth();

    return user?.isSuperAdmin ? <SuperAdminAuditLog /> : <CompanyAuditLog />;
};
