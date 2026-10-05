import type { ApiDashboardMonetario } from '../types/api-dashboard-summary';
import type { StatCardCambio } from '../types/stat-card-cambio';
import { formatVariacion } from './format-variacion';

/** Comparación del bloque: vs. mes anterior en la insignia y vs. año anterior en el texto. `favorableSiBaja` aplica a gastos. */
export function buildCambioStatCard(bloque: ApiDashboardMonetario, favorableSiBaja = false): StatCardCambio {
    const porcentaje = bloque.mes_anterior.variacion;

    return {
        porcentaje,
        favorable: porcentaje === null || (favorableSiBaja ? porcentaje <= 0 : porcentaje >= 0),
        referencia: `vs. mes anterior · vs. año anterior ${formatVariacion(bloque.anio_anterior.variacion)}`,
    };
}
