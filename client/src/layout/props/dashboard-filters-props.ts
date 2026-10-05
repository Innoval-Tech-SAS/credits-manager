import type { ApiCompany } from '../../company/types/api-company';
import type { DashboardFiltrosValor } from '../types/dashboard-filtros-valor';

export type DashboardFiltersProps = {
    filtros: DashboardFiltrosValor;
    onChange: (cambios: Partial<DashboardFiltrosValor>) => void;
    /** Solo el superadmin recibe las empresas; `null` oculta el selector. */
    empresas: ApiCompany[] | null;
    mostrarAsesor: boolean;
    mostrarCliente: boolean;
};
