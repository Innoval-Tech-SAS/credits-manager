/** Raw shape returned by `GET /conciliacion/cuentas-bancarias/{id}/extractos` (rows) and `POST .../extractos`. */
export type ApiBankStatement = {
    id: number;
    cuenta_bancaria_id: number;
    fecha_desde: string;
    fecha_hasta: string;
    saldo_anterior: string;
    total_abonos: string;
    total_cargos: string;
    saldo_actual: string;
    estado: 'cargando' | 'cargado' | 'rechazado' | 'cerrado';
    motivo_rechazo: string | null;
};
