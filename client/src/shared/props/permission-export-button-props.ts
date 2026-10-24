export type PermissionExportButtonProps = {
    /** Permiso "modulo.accion" de esta exportación (p. ej. `cartera.exportar_listado`): el mismo que exige su ruta. */
    permiso: string;
    /** Ruta `POST` de la exportación (p. ej. `/cartera/exportar`). */
    endpoint: string;
    /** Filtros vigentes en pantalla: el archivo sale con los mismos que ve el usuario. */
    filters: Record<string, string>;
    disabled?: boolean;
    onRequested?: () => void;
    onError?: (message: string) => void;
};
