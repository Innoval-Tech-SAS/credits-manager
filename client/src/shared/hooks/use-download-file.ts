import { useCallback, useState } from 'react';

import { API_PREFIX, API_ROOT } from '../constants/api';
import { SESSION_EXPIRED_EVENT } from '../constants/auth-events';
import { readFilenameFromDisposition } from '../utils/read-filename-from-disposition';
import { saveBlob } from '../utils/save-blob';

const GENERIC_ERROR = 'Ocurrió un error inesperado.';

/** Descarga un archivo binario de la API (PDF, Excel…) con la sesión del usuario y lo guarda con el nombre que manda el servidor. */
export function useDownloadFile() {
    const [isDownloading, setIsDownloading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const download = useCallback(async (path: string, fallbackFilename: string): Promise<void> => {
        setIsDownloading(true);
        setError(null);

        try {
            const response = await fetch(`${API_ROOT}${API_PREFIX}${path}`, {
                credentials: 'include',
                headers: { Accept: 'application/pdf, application/octet-stream, application/json' },
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

            saveBlob(readFilenameFromDisposition(response.headers.get('Content-Disposition')) ?? fallbackFilename, blob);
        } catch (caught) {
            setError(caught instanceof Error && !(caught instanceof TypeError) ? caught.message : GENERIC_ERROR);
        } finally {
            setIsDownloading(false);
        }
    }, []);

    return { download, isDownloading, error };
}
