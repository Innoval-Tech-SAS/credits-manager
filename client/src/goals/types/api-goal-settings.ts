/** Raw shape returned by `GET /metas/configuracion` y `PUT /metas/configuracion` (parámetros de la empresa). */
export type ApiGoalSettings = {
    tolerancia_riesgo_puntos: number;
    cumplimiento_parcial_minimo: number;
    alerta_alcanzada_activa: boolean;
    umbral_alcanzada: number;
    modo_colocacion_refinanciaciones: 'excluir' | 'solo_dinero_nuevo' | 'total';
    base_recaudo: 'total' | 'solo_capital' | 'solo_interes';
    distribucion_obligatoria_completa: boolean;
    meta_consolidada_visible_asesor: boolean;
    dia_de_cierre: 'ultimo_dia' | 'primer_dia_siguiente';
};
