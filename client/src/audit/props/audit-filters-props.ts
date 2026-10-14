import type { ApiCompany } from '../../company/types/api-company';
import type { AuditFilters } from '../types/audit-filters';
import type { AuditUserOption } from '../types/audit-user-option';

export type AuditFiltersProps = {
    filters: AuditFilters;
    /** Solo el superadmin recibe empresas (y por tanto ve el filtro por empresa); el resto recibe `null`. */
    companies: ApiCompany[] | null;
    /** Catálogo para el filtro "Autor": todos los usuarios de la empresa, o de la plataforma si es superadmin. */
    users: AuditUserOption[];
    onChange: (partial: Partial<AuditFilters>) => void;
    onReset: () => void;
    /** Cambia al limpiar: fuerza a remontar los `Select`, que guardan su valor internamente. */
    resetToken: number;
};
