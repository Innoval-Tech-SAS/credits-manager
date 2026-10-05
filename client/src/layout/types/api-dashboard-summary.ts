/** Raw shape of a `pagos_hoy` row within `GET /dashboard`. */
export type ApiDashboardPagoHoy = {
    cuota_id: number;
    credito_id: number;
    cliente_nombre: string;
    numero_cuota: number;
    /** Decimal strings (ej. "12345.66"), no numbers — vienen de `App\Core\Dinero\Dinero`. */
    valor: string;
    pendiente: string;
};

/** Variación en porcentaje (no dinero); `null` cuando el período de referencia no tiene valor. */
export type ApiDashboardComparacion = {
    valor: string;
    variacion: number | null;
};

export type ApiDashboardMonetario = {
    valor: string;
    mes_anterior: ApiDashboardComparacion;
    anio_anterior: ApiDashboardComparacion;
};

export type ApiDashboardColocacion = ApiDashboardMonetario & {
    cantidad: number;
};

export type ApiDashboardCartera = {
    pendiente: string;
    al_dia: string;
    vencida: string;
    /** Créditos en mora por rango de días; `creditos` suma el total en mora. */
    mora: {
        creditos: number;
        saldo: string;
        promedio: string;
        rangos: { clave: string; creditos: number; saldo: string }[];
    };
};

/** Raw shape returned by `GET /dashboard`. Los bloques en `null` no se muestran: el usuario no tiene el permiso del módulo. */
export type ApiDashboardSummary = {
    periodo: { mes: string; inicio: string; fin: string };
    historico: {
        /** 12 meses `YYYY-MM` que terminan en el período elegido. */
        meses: string[];
        colocacion: string[] | null;
        recaudo: string[] | null;
        gastos: string[] | null;
    };
    metricas: {
        clientes_activos: number;
        creditos_activos: number;
        colocacion: ApiDashboardColocacion | null;
        recaudo: ApiDashboardMonetario | null;
        gastos: ApiDashboardMonetario | null;
        cartera: ApiDashboardCartera | null;
    };
    pagos_hoy: ApiDashboardPagoHoy[];
};
