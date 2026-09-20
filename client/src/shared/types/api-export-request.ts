import type { ExportFormat } from './export-format';

export type ExportStatus = 'pendiente' | 'procesando' | 'completada' | 'fallida';

/** Raw shape returned by `POST /auditoria/bitacora/exportar` (202) and by the rows of `GET /exportaciones`. */
export type ApiExportRequest = {
    id: number;
    empresa_id: number | null;
    usuario_id: number;
    reporte: string;
    formato: ExportFormat;
    estado: ExportStatus;
    filtros: Record<string, string | number> | null;
    filas: number;
    nombre_archivo: string | null;
    tamano_bytes: number | null;
    error: string | null;
    iniciada_en: string | null;
    completada_en: string | null;
    creado_en: string | null;
};
