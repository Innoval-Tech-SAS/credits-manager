import type { ApiEmployer } from '../../employers/types/api-employer';

/** Raw shape of a period nested in `historial_empleadores` (`GET /clientes/{id}/resumen`). */
export type ApiEmploymentHistory = {
    id: number;
    empleador_id: number;
    empleador: ApiEmployer;
    fecha_inicio: string;
    fecha_fin: string | null;
    vigente: boolean;
};
