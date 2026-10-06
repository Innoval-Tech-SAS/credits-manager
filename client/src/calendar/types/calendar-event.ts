import type { EventInput } from '@fullcalendar/core';
import type { InstallmentStatus } from '../../credits/types/installment-status';
import type { CalendarInstallmentColor } from './api-calendar-installment';

export type CalendarEventProps = {
    color: CalendarInstallmentColor;
    creditoId: number;
    clienteNombre: string;
    numeroCuota: number;
    /** Decimal string (ej. "12345.66"), viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
    fechaVencimiento: string;
    estado: InstallmentStatus;
};

export type CalendarEvent = EventInput & {
    extendedProps: CalendarEventProps;
};
