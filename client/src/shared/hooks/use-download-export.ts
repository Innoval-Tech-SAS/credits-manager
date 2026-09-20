import { useCallback, useState } from 'react';

import { API_PREFIX, API_ROOT } from '../constants/api';
import { SESSION_EXPIRED_EVENT } from '../constants/auth-events';
import { readFilenameFromDisposition } from '../utils/read-filename-from-disposition';
import { saveBlob } from '../utils/save-blob';

const GENERIC_ERROR = 'Ocurrió un error inesperado.';

/** Descarga el archivo de una exportación ya generada (`GET /exportaciones/{id}/descargar`, solo del solicitante). */
export function useDownloadExport() {
    const [isDownloading, setIsDownloading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const download = useCallback(async (exportId: number): Promise<void> => {
        setIsDownloading(true);
        setError(null);

        try {
            const response = await fetch(`${API_ROOT}${API_PREFIX}/exportaciones/${exportId}/descargar`, {
                credentials: 'include',
                headers: { Accept: 'application/octet-stream, application/json' },
            });

            if (response.status === 401) {
                window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT));
                return;
            }

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error(body?.message ?? 'No se pudo descargar el archivo.');
            }

            const blob = await response.blob();

            saveBlob(readFilenameFromDisposition(response.headers.get('Content-Disposition')) ?? `exportacion-${exportId}`, blob);
        } catch (caught) {
            setError(caught instanceof Error && !(caught instanceof TypeError) ? caught.message : GENERIC_ERROR);
        } finally {
            setIsDownloading(false);
        }
    }, []);

    return { download, isDownloading, error };
}
