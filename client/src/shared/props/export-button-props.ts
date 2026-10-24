import type { ApiExportRequest } from '../types/api-export-request';
import type { ExportFormat } from '../types/export-format';

export type ExportButtonProps = {
    /** Ruta `POST` del reporte (p. ej. `/auditoria/bitacora/exportar`). */
    endpoint: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    /** Formatos que se piden en segundo plano; por defecto Excel y CSV. */
    formats?: ExportFormat[];
    /** Ruta `GET` del PDF del mismo reporte; si viene, se agrega la opción PDF, que se descarga al momento. */
    pdfEndpoint?: string;
    pdfFilename?: string;
    /** Lado del que se abre el menú: `right` (por defecto) si el botón está a la derecha de la barra, `left` si está a la izquierda. */
    align?: 'left' | 'right';
    disabled?: boolean;
    onRequested?: (request: ApiExportRequest) => void;
    onError?: (message: string) => void;
};
