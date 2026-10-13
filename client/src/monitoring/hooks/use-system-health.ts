import { useCallback, useEffect, useRef, useState } from 'react';

import { createEcho } from '../../notifications/utils/create-echo';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { FALLBACK_POLL_MS, LIVE_TIMEOUT_MS } from '../constants/live-status';
import type { ApiSystemHealth } from '../types/api-system-health';
import type { LiveStatus } from '../types/live-status';
import type { UsageSample } from '../types/usage-sample';
import { appendUsageSample } from '../utils/append-usage-sample';

const CHANNEL = 'monitoreo';
const CHECK_INTERVAL_MS = 5_000;

/**
 * Carga el estado una vez y luego lo recibe por Reverb: el monitor solo publica mientras este canal
 * tiene suscriptores. Si dejan de llegar muestras (Reverb o el monitor caídos, que el propio panel
 * debe poder mostrar), la página vuelve a consultar la API cada pocos segundos.
 */
export function useSystemHealth() {
    const { sendRequest } = useSendRequest();
    const [health, setHealth] = useState<ApiSystemHealth | null>(null);
    const [history, setHistory] = useState<UsageSample[]>([]);
    const [liveStatus, setLiveStatus] = useState<LiveStatus>('conectando');
    const [error, setError] = useState<string | null>(null);

    const lastPushAt = useRef(0);
    const lastFetchAt = useRef(0);

    const receive = useCallback((snapshot: ApiSystemHealth) => {
        setHealth(snapshot);
        setHistory((current) => appendUsageSample(current, snapshot));
    }, []);

    const fetchSnapshot = useCallback(async () => {
        lastFetchAt.current = Date.now();

        try {
            receive(await sendRequest<ApiSystemHealth>('/monitoreo/estado'));
            setError(null);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    }, [receive, sendRequest]);

    useEffect(() => {
        const startedAt = Date.now();
        const echo = createEcho();

        echo.private(CHANNEL)
            .listen('.estado.actualizado', (event: { estado: ApiSystemHealth }) => {
                lastPushAt.current = Date.now();
                setLiveStatus('en_vivo');
                receive(event.estado);
            })
            .error(() => setLiveStatus('sin_conexion'));

        void fetchSnapshot();

        const timer = window.setInterval(() => {
            const now = Date.now();
            const isLive = now - lastPushAt.current < LIVE_TIMEOUT_MS;

            if (isLive || now - startedAt < LIVE_TIMEOUT_MS) {
                return;
            }

            setLiveStatus('sin_conexion');

            if (now - lastFetchAt.current >= FALLBACK_POLL_MS) {
                void fetchSnapshot();
            }
        }, CHECK_INTERVAL_MS);

        return () => {
            window.clearInterval(timer);
            echo.leave(CHANNEL);
            echo.disconnect();
        };
    }, [fetchSnapshot, receive]);

    return { health, history, liveStatus, error };
}
