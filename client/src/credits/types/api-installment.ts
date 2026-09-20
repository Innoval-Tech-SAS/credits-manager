import type { InstallmentStatus } from './installment-status';

/** Raw shape of a `cuotas` row nested in `ApiCredit` (`GET /creditos/{id}` and related endpoints). */
export type ApiInstallment = {
    id: number;
    credito_id: number;
    numero_cuota: number;
    fecha_vencimiento: string;
    valor: string;
    valor_interes: string;
    valor_capital: string;
    saldo_capital: string;
    valor_pagado: string;
    estado: InstallmentStatus;
};
