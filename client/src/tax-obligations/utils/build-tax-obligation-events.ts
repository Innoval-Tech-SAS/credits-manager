import type { ApiTaxObligation } from '../types/api-tax-obligation';
import type { TaxObligationEvent } from '../types/api-tax-obligation-event';

export function buildTaxObligationEvents(obligaciones: ApiTaxObligation[]): TaxObligationEvent[] {
    return obligaciones.map((obligacion) => ({
        id: `obligacion-${obligacion.id}`,
        title: `${obligacion.tipo_obligacion.nombre} · ${obligacion.periodo}`,
        start: obligacion.fecha_limite,
        allDay: true,
        extendedProps: {
            color: obligacion.color,
            obligacion,
        },
    }));
}
