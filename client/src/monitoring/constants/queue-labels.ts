export const QUEUE_LABELS: Record<string, string> = {
    exportaciones: 'Exportaciones',
    importaciones: 'Importaciones',
    conciliacion: 'Conciliación',
    correos: 'Correos',
};

export const QUEUE_OPTIONS = Object.entries(QUEUE_LABELS).map(([value, label]) => ({ value, label }));
