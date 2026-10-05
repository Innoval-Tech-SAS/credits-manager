export type ApiGoalType = 'colocacion' | 'recaudo';

export type ApiGoalStatus = 'en_curso' | 'alcanzada' | 'en_riesgo' | 'cumplida' | 'cumplida_parcial' | 'incumplida';

/** Montos como cadenas decimales; porcentaje con dos decimales. */
export type ApiGoalCumplimiento = {
    objetivo: string;
    ejecutado: string;
    porcentaje: string;
    brecha: string;
    estado: ApiGoalStatus;
};

export type ApiGoalAsesor = {
    usuario_id: number;
    nombre: string;
    cumplimiento: ApiGoalCumplimiento;
};

export type ApiGoal = {
    id: number;
    tipo: ApiGoalType;
    valor_objetivo: string;
    cerrado: boolean;
    empresa: ApiGoalCumplimiento;
    asesores: ApiGoalAsesor[];
};

/** Raw shape returned by `GET /metas?periodo=YYYY-MM`. `ver_detalle` es falso para un asesor con la meta consolidada. */
export type ApiGoalsPeriod = {
    periodo: string;
    porcentaje_de_dias: string;
    ver_detalle: boolean;
    metas: ApiGoal[];
};
