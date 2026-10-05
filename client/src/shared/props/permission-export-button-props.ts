export type PermissionExportButtonProps = {
    /** Módulo cuyo permiso `exportar` habilita el botón (p. ej. `cartera`, `creditos`). */
    modulo: string;
    /** Ruta `POST` de la exportación (p. ej. `/cartera/exportar`). */
    endpoint: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    disabled?: boolean;
    onRequested?: () => void;
    onError?: (message: string) => void;
};
