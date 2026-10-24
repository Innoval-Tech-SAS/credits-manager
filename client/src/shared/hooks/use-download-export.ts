import { useCallback } from 'react';

import { useDownloadFile } from './use-download-file';

/** Descarga el archivo de una exportación ya generada (`GET /exportaciones/{id}/descargar`, solo del solicitante). */
export function useDownloadExport() {
    const { download: downloadFile, isDownloading, error } = useDownloadFile();

    const download = useCallback(
        async (exportId: number): Promise<void> => {
            await downloadFile(`/exportaciones/${exportId}/descargar`, `exportacion-${exportId}`);
        },
        [downloadFile],
    );

    return { download, isDownloading, error };
}
