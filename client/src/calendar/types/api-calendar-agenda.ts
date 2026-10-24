import type { ApiCalendarInstallment } from './api-calendar-installment';

/** Raw shape returned by `GET /calendario?mes=YYYY-MM`. */
export type ApiCalendarAgenda = {
    cuotas: ApiCalendarInstallment[];
};
