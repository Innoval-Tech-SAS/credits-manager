/** Raw shape returned by `GET /contabilidad/plan-cuentas` (rows), `POST` and `PATCH`. */
export type ApiAccount = {
    id: number;
    codigo: string;
    nombre: string;
    naturaleza: 'debito' | 'credito';
    nivel: number;
    cuenta_padre_id: number | null;
    tipo: 'activo' | 'pasivo' | 'patrimonio' | 'ingreso' | 'gasto' | 'costo' | 'orden';
    permite_movimiento: boolean;
    exige_tercero: boolean;
    exige_documento_cruce: boolean;
    exige_vencimiento: boolean;
    exige_centro_costo: boolean;
    activa: boolean;
};
