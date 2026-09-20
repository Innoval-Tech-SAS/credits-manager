/** Raw shape of a `pagos_hoy` row within `GET /dashboard`. */
export type ApiDashboardPagoHoy = {
    cuota_id: number;
    credito_id: number;
    cliente_nombre: string;
    numero_cuota: number;
    valor: number;
    pendiente: number;
};

/** Raw shape returned by `GET /dashboard`. */
export type ApiDashboardSummary = {
    clientes_activos: number;
    creditos_activos: number;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    cartera_pendiente: string;
    creditos_en_mora: number;
    pagos_hoy: ApiDashboardPagoHoy[];
};
