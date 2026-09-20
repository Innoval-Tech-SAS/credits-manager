import type { ApiCompany } from '../../company/types/api-company';

export type AuditLogViewProps = {
    /** Empresas de la plataforma (solo superadmin); `null` oculta el filtro y la columna de empresa. */
    companies: ApiCompany[] | null;
};
