/** Raw shape returned by `GET /importaciones/{id}/errores` (paginated rows). */
export type ApiImportError = {
    id: number;
    fila: number | null;
    columna: string | null;
    severidad: 'error' | 'aviso';
    fase: 'validacion' | 'escritura';
    motivo: string;
};
