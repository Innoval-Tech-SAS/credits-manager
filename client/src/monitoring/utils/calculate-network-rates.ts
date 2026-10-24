import type { UsageSample } from '../types/usage-sample';

/**
 * Convierte los contadores acumulados de red en bytes/segundo, comparando cada muestra con la anterior.
 * La primera muestra del historial no tiene con qué compararse y queda en 0.
 */
export function calculateNetworkRates(history: UsageSample[]): { recepcion: number[]; envio: number[] } {
    const recepcion: number[] = [];
    const envio: number[] = [];

    history.forEach((muestra, indice) => {
        const anterior = history[indice - 1];
        const segundos = anterior ? (muestra.timestamp - anterior.timestamp) / 1000 : 0;

        if (!anterior || !muestra.red || !anterior.red || segundos <= 0) {
            recepcion.push(0);
            envio.push(0);

            return;
        }

        recepcion.push(Math.max(0, (muestra.red.rx_bytes - anterior.red.rx_bytes) / segundos));
        envio.push(Math.max(0, (muestra.red.tx_bytes - anterior.red.tx_bytes) / segundos));
    });

    return { recepcion, envio };
}
