import type { ApiCompany } from '../../company/types/api-company';
import type { AuditUserOption } from '../types/audit-user-option';

export type AuditLogViewProps = {
    /** Empresas de la plataforma (solo superadmin); `null` oculta el filtro y la columna de empresa. */
    companies: ApiCompany[] | null;
    /** Catálogo para el filtro "Autor": todos los usuarios de la empresa, o de la plataforma si es superadmin. */
    users: AuditUserOption[];
};
