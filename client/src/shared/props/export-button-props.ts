import type { ApiExportRequest } from '../types/api-export-request';

export type ExportButtonProps = {
    /** Ruta `POST` del reporte (p. ej. `/auditoria/bitacora/exportar`). */
    endpoint: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    disabled?: boolean;
    onRequested?: (request: ApiExportRequest) => void;
    onError?: (message: string) => void;
};
