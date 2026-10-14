import { describe, expect, it } from 'vitest';

import { USAGE_HISTORY_SIZE } from '../constants/live-status';
import type { ApiSystemHealth } from '../types/api-system-health';
import { appendUsageSample } from './append-usage-sample';

function snapshot(generadoEn: string, cpu: number): ApiSystemHealth {
    return {
        servidor: {
            cpu: { porcentaje: cpu, nucleos: 2 },
            memoria: { total_bytes: 100, usada_bytes: 40, porcentaje: 40, swap_total_bytes: 0, swap_usada_bytes: 0 },
            carga: [0.5, 0.4, 0.3],
            disco: null,
            red: null,
            encendido_segundos: 60,
        },
        servicios: [],
        colas: [],
        auditoria: { en_stream: 0, pendientes: 0 },
        generado_en: generadoEn,
    };
}

describe('appendUsageSample', () => {
    it('agrega la muestra con CPU y memoria', () => {
        const history = appendUsageSample([], snapshot('2026-10-13T10:00:05-05:00', 12.5));

        expect(history).toHaveLength(1);
        expect(history[0]).toMatchObject({ cpu: 12.5, memoria: 40 });
    });

    it('ignora la misma instantánea recibida dos veces (carga inicial y primer empuje)', () => {
        const once = appendUsageSample([], snapshot('2026-10-13T10:00:05-05:00', 10));

        expect(appendUsageSample(once, snapshot('2026-10-13T10:00:05-05:00', 10))).toBe(once);
    });

    it('conserva solo las últimas muestras', () => {
        let history = appendUsageSample([], snapshot('2026-10-13T10:00:00-05:00', 0));

        for (let i = 1; i <= USAGE_HISTORY_SIZE + 5; i++) {
            history = appendUsageSample(history, snapshot(new Date(Date.parse('2026-10-13T15:00:00Z') + i * 5_000).toISOString(), i));
        }

        expect(history).toHaveLength(USAGE_HISTORY_SIZE);
        expect(history.at(-1)?.cpu).toBe(USAGE_HISTORY_SIZE + 5);
    });
});
