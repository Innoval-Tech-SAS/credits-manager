import { describe, expect, it } from 'vitest';

import type { UsageSample } from '../types/usage-sample';
import { calculateNetworkRates } from './calculate-network-rates';

function muestra(timestamp: number, red: UsageSample['red']): UsageSample {
    return { hora: '', timestamp, cpu: 0, memoria: 0, red };
}

describe('calculateNetworkRates', () => {
    it('la primera muestra queda en 0 por no tener con qué compararse', () => {
        const { recepcion, envio } = calculateNetworkRates([muestra(0, { rx_bytes: 1000, tx_bytes: 500 })]);

        expect(recepcion).toEqual([0]);
        expect(envio).toEqual([0]);
    });

    it('calcula bytes/segundo entre dos muestras consecutivas', () => {
        const history = [muestra(0, { rx_bytes: 1000, tx_bytes: 500 }), muestra(5_000, { rx_bytes: 6000, tx_bytes: 2500 })];

        const { recepcion, envio } = calculateNetworkRates(history);

        expect(recepcion).toEqual([0, 1000]);
        expect(envio).toEqual([0, 400]);
    });

    it('nunca entrega una tasa negativa si el contador se reinició', () => {
        const history = [muestra(0, { rx_bytes: 5000, tx_bytes: 5000 }), muestra(5_000, { rx_bytes: 100, tx_bytes: 100 })];

        const { recepcion, envio } = calculateNetworkRates(history);

        expect(recepcion[1]).toBe(0);
        expect(envio[1]).toBe(0);
    });

    it('sin datos de red en alguna de las dos muestras, queda en 0', () => {
        const history = [muestra(0, null), muestra(5_000, { rx_bytes: 1000, tx_bytes: 1000 })];

        const { recepcion, envio } = calculateNetworkRates(history);

        expect(recepcion).toEqual([0, 0]);
        expect(envio).toEqual([0, 0]);
    });
});
