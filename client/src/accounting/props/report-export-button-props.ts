export type ReportExportButtonProps = {
    /** Ruta `POST` de la exportación del reporte (p. ej. `/contabilidad/libro-diario/exportar`). */
    endpoint: string;
    /** Permisos "modulo.accion" que habilitan Excel y CSV de este reporte (basta uno), los mismos que exige su ruta. */
    exportPermissions: string[];
    /** Ruta `GET` del PDF del mismo reporte (p. ej. `/contabilidad/libro-diario/pdf`); sin ella no hay opción PDF. */
    pdfEndpoint?: string;
    pdfFilename?: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    disabled?: boolean;
};
