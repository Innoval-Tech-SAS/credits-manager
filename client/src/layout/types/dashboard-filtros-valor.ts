import type { ApiClient } from '../../clients/types/api-client';
import type { ApiCredit } from '../../credits/types/api-credit';

/** Estado de los filtros del dashboard. `null` significa "todos" o "sin elegir" según el campo. */
export type DashboardFiltrosValor = {
    /** `YYYY-MM`. */
    periodo: string;
    /** Solo el superadmin la elige; el resto trabaja en su empresa. */
    empresaId: number | null;
    asesorId: number | null;
    cliente: ApiClient | null;
    estado: ApiCredit['estado'] | null;
};
