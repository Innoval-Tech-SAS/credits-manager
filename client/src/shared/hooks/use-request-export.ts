import { useCallback } from 'react';

import type { ApiExportRequest } from '../types/api-export-request';
import type { ExportFormat } from '../types/export-format';
import { useSendRequest } from './use-send-request';

/**
 * Pide una exportación con los filtros vigentes de la pantalla. El backend responde 202 y la genera
 * en segundo plano: el archivo llega después por notificación (WebSocket) y se descarga con `useDownloadExport`.
 */
export function useRequestExport() {
    const { sendRequest, isLoading, error } = useSendRequest();

    const requestExport = useCallback(
        async (endpoint: string, format: ExportFormat, filters: Record<string, string>): Promise<ApiExportRequest> => {
            const activeFilters = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));

            const response = await sendRequest<{ exportacion: ApiExportRequest }>(endpoint, {
                method: 'POST',
                body: { ...activeFilters, formato: format },
            });

            return response.exportacion;
        },
        [sendRequest],
    );

    return { requestExport, isRequesting: isLoading, error };
}
