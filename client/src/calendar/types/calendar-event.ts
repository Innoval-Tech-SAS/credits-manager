import type { EventInput } from '@fullcalendar/core';
import type { CalendarInstallmentColor } from './api-calendar-installment';

export type CalendarEvent = EventInput & {
    extendedProps: {
        color: CalendarInstallmentColor;
        creditoId: number;
    };
};
