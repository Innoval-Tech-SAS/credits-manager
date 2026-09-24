import { useEffect, useState } from 'react';

import { useSendRequest } from './use-send-request';

/** Con `endpoint` nulo no pide nada: sirve para selectores que dependen de otro valor aún no elegido. */
export function useResource<T>(endpoint: string | null) {
    const { sendRequest, isLoading, error } = useSendRequest();
    const [data, setData] = useState<T | null>(null);
    const [loadedEndpoint, setLoadedEndpoint] = useState<string | null>(null);
    const [refreshToken, setRefreshToken] = useState(0);

    if (endpoint !== loadedEndpoint && data !== null) {
        setData(null);
    }

    useEffect(() => {
        if (endpoint === null) {
            return;
        }

        let isCancelled = false;

        sendRequest<T>(endpoint)
            .then((response) => {
                if (!isCancelled) {
                    setData(response);
                    setLoadedEndpoint(endpoint);
                }
            })
            .catch(() => {});

        return () => {
            isCancelled = true;
        };
    }, [endpoint, sendRequest, refreshToken]);

    const refetch = () => {
        setRefreshToken((token) => token + 1);
    };

    return { data, isLoading, error, refetch };
}
