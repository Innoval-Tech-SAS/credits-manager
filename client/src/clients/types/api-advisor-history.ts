import type { ApiAdvisorRef } from './api-advisor';

/** Raw shape of a row in `GET /clientes/{id}/asesor-historial` (solo administradores). */
export type ApiAdvisorHistory = {
    id: number;
    asesor_id: number;
    asesor: ApiAdvisorRef;
    asesor_anterior_id: number | null;
    asesor_anterior: ApiAdvisorRef | null;
    autor: ApiAdvisorRef | null;
    motivo: string | null;
    creado_en: string;
};
