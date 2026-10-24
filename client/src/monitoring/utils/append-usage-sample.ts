import { USAGE_HISTORY_SIZE } from '../constants/live-status';
import type { ApiSystemHealth } from '../types/api-system-health';
import type { UsageSample } from '../types/usage-sample';

const HOUR_FORMAT = new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'America/Bogota' });

/** Agrega la muestra al final y descarta las más viejas; ignora una instantánea repetida. */
export function appendUsageSample(history: UsageSample[], health: ApiSystemHealth): UsageSample[] {
    const generadoEn = new Date(health.generado_en);
    const hora = HOUR_FORMAT.format(generadoEn);

    if (history.at(-1)?.hora === hora) {
        return history;
    }

    const sample: UsageSample = {
        hora,
        timestamp: generadoEn.getTime(),
        cpu: health.servidor.cpu?.porcentaje ?? 0,
        memoria: health.servidor.memoria?.porcentaje ?? 0,
        red: health.servidor.red,
    };

    return [...history, sample].slice(-USAGE_HISTORY_SIZE);
}
