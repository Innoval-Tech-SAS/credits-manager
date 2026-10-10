/** Raw shape returned by `GET /conciliacion/cuentas-bancarias` (rows) and `POST`. */
export type ApiBankAccount = {
    id: number;
    banco: 'bancolombia';
    numero_cuenta: string;
    tipo_cuenta: 'ahorros' | 'corriente';
    alias: string | null;
    plan_cuenta_id: number;
    plan_cuenta_puente_id: number | null;
    activa: boolean;
    plan_cuenta?: { id: number; codigo: string; nombre: string } | null;
    plan_cuenta_puente?: { id: number; codigo: string; nombre: string } | null;
};
