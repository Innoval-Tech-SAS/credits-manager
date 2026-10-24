/** Nombre y rol de cada servicio del compose, en el orden en que los devuelve la API. */
export const SERVICE_LABELS: Record<string, { nombre: string; rol: string }> = {
    database: { nombre: 'Base de datos', rol: 'MySQL 8.4' },
    redis: { nombre: 'Redis', rol: 'Colas y bitácora' },
    api: { nombre: 'API', rol: 'Laravel (PHP-FPM)' },
    nginx: { nombre: 'Nginx', rol: 'SPA, proxy y HTTPS' },
    reverb: { nombre: 'Reverb', rol: 'Tiempo real' },
    phpmyadmin: { nombre: 'phpMyAdmin', rol: 'Administración de la BD' },
    'auditoria-listener': { nombre: 'Listener de auditoría', rol: 'Redis → bitácora' },
    'exportaciones-worker': { nombre: 'Worker de exportaciones', rol: 'Cola exportaciones' },
    'importaciones-worker': { nombre: 'Worker de importaciones', rol: 'Cola importaciones' },
    'conciliacion-worker': { nombre: 'Worker de conciliación', rol: 'Cola conciliacion' },
    'correos-worker': { nombre: 'Worker de correos', rol: 'Cola correos' },
    scheduler: { nombre: 'Scheduler', rol: 'Tareas programadas' },
    monitor: { nombre: 'Monitor', rol: 'Publica este panel' },
};
