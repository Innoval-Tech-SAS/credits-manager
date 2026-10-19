/** Fila de cuenta o de grupo de cuentas (primeros 2 dígitos del código), con su valor ya firmado. */
export type ApiDashboardAccountRow = {
    codigo: string;
    nombre: string;
    valor: string;
};

export type ApiDashboardCarteraNeta = {
    bruta: string;
    deterioro: string;
    neta: string;
    /** String decimal, ej. "12.34" (no number). */
    porcentaje_deterioro: string;
};

/** Raw shape returned by `GET /contabilidad/dashboard/situacion`. */
export type ApiDashboardSituacion = {
    total_activos: string;
    total_pasivos: string;
    total_patrimonio: string;
    utilidad: string;
    cuadra: boolean;
    composicion_activo: ApiDashboardAccountRow[];
    composicion_pasivo: ApiDashboardAccountRow[];
    composicion_patrimonio: ApiDashboardAccountRow[];
    cartera_neta: ApiDashboardCarteraNeta;
};

export type ApiDashboardCostCenterExpenseRow = {
    centro_costo_id: number | null;
    nombre: string;
    valor: string;
};

export type ApiDashboardMonthlyRow = {
    mes: string;
    ingresos: string;
    gastos: string;
    utilidad: string;
};

/** Raw shape returned by `GET /contabilidad/dashboard/resultados`. */
export type ApiDashboardResultados = {
    ingresos: ApiDashboardAccountRow[];
    gastos_por_grupo: ApiDashboardAccountRow[];
    gastos_por_centro_costo: ApiDashboardCostCenterExpenseRow[];
    total_ingresos: string;
    total_gastos: string;
    utilidad_periodo: string;
    utilidad_acumulada_anio: string;
    utilidad_mismo_periodo_anio_anterior: string;
    serie_mensual: ApiDashboardMonthlyRow[];
};

export type ApiDashboardPendingReopenRequest = {
    id: number;
    periodo: { anio: number; mes: number } | null;
    solicitante: string | null;
    creado_en: string;
};

export type ApiDashboardClosingBalanceSummary = {
    cuadra: boolean;
    diferencia: string;
};

export type ApiDashboardEventoSinRegla = {
    evento: string;
    etiqueta: string;
};

/** Raw shape returned by `GET /contabilidad/dashboard/cierre`. */
export type ApiDashboardCierre = {
    periodo: { anio: number; mes: number; estado: string };
    solicitudes_reapertura_pendientes: ApiDashboardPendingReopenRequest[];
    controles_cierre: {
        cuentas_por_cobrar: ApiDashboardClosingBalanceSummary;
        cuentas_por_pagar: ApiDashboardClosingBalanceSummary;
        saldos_contrarios: number;
        documentos_inconsistentes: number;
        conciliacion_bancaria: number;
    };
    cartera_operativa_vs_contable: {
        saldo_operativo: string;
        saldo_contable: string;
        diferencia: string;
    };
    comprobantes_pendientes_revision: number;
    devengo_intereses: {
        corrio_este_mes: boolean;
        resultado: string | null;
        finalizado_en: string | null;
    };
    eventos_sin_regla: ApiDashboardEventoSinRegla[];
};
