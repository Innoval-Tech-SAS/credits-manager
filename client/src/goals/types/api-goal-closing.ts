import type { ApiGoalStatus, ApiGoalType } from './api-goals-period';

/** Raw shape of a row in `GET /metas/cierres`. `asesor_id` nulo es el cierre de la meta de empresa. */
export type ApiGoalClosing = {
    id: number;
    periodo: string;
    tipo: ApiGoalType;
    asesor_id: number | null;
    usuario: string | null;
    cierre_anterior_id: number | null;
    objetivo: string;
    ejecutado: string;
    porcentaje: string;
    estado: ApiGoalStatus;
    motivo: string | null;
    cerrado_en: string;
};
