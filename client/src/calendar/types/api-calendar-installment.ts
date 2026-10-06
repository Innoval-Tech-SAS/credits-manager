import type { InstallmentStatus } from '../../credits/types/installment-status';

export type CalendarInstallmentColor = 'success' | 'warning' | 'danger';

/** Raw shape of an installment entry within `GET /calendario`. */
export type ApiCalendarInstallment = {
    id: number;
    credito_id: number;
    cliente_nombre: string;
    numero_cuota: number;
    /** Decimal string (ej. "12345.66"), viene de `AppCoreDineroDinero`. */
    saldo: string;
    /** Decimales en string, vienen de `App\Core\Dinero\Dinero`. */
    valor_total: string;
    valor_pagado: string;
    valor_capital: string;
    valor_interes: string;
    valor_otros_gastos: string;
    cliente: {
        id: number;
        nombre: string;
        tipo_documento: string;
        numero_documento: string;
        celular: string | null;
    };
    /** Créditos del cliente con la entidad, sin cancelados ni eliminados. */
    creditos_con_entidad: number;
    fecha_vencimiento: string;
    estado: InstallmentStatus;
    color: CalendarInstallmentColor;
};
