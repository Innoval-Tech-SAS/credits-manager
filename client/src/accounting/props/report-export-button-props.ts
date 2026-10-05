export type ReportExportButtonProps = {
    /** Ruta `POST` de la exportación del reporte (p. ej. `/contabilidad/libro-diario/exportar`). */
    endpoint: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    disabled?: boolean;
};
