import { formatMoney } from '../../shared/utils/format-money';
import type { ApiCalendarAgenda } from '../types/api-calendar-agenda';
import type { CalendarEvent } from '../types/calendar-event';

export function buildCalendarEvents(agenda: ApiCalendarAgenda): CalendarEvent[] {
    return agenda.cuotas.map((cuota) => ({
        id: `cuota-${cuota.id}`,
        title: `${cuota.cliente_nombre} · Cuota #${cuota.numero_cuota} · ${formatMoney(cuota.saldo)}`,
        start: cuota.fecha_vencimiento,
        allDay: true,
        extendedProps: { color: cuota.color, creditoId: cuota.credito_id },
    }));
}
