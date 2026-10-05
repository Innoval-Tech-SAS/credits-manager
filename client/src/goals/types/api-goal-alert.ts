import type { ApiGoalStatus, ApiGoalType } from './api-goals-period';

/** Raw shape of a row in `GET /metas/alertas`. `usuario_id` nulo es una alerta de la meta de empresa. */
export type ApiGoalAlert = {
    id: number;
    meta_id: number;
    tipo: ApiGoalType;
    periodo: string;
    estado: ApiGoalStatus;
    usuario_id: number | null;
    asesor: string | null;
    generada_en: string;
    vista_en: string | null;
};
