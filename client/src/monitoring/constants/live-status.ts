import type { LiveStatus } from '../types/live-status';

export const LIVE_STATUS_LABELS: Record<LiveStatus, string> = {
    conectando: 'Conectando…',
    en_vivo: 'En vivo',
    sin_conexion: 'Sin tiempo real · actualiza cada 15 s',
};

export const LIVE_STATUS_CLASSES: Record<LiveStatus, string> = {
    conectando: 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
    en_vivo: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    sin_conexion: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400',
};

/** El monitor publica cada 5 s: sin muestras en este tiempo se da el tiempo real por caído. */
export const LIVE_TIMEOUT_MS = 15_000;

/** Mientras no haya tiempo real, la página consulta la API con esta frecuencia. */
export const FALLBACK_POLL_MS = 15_000;

/** Muestras que guarda la gráfica de uso: 5 minutos a una muestra cada 5 s. */
export const USAGE_HISTORY_SIZE = 60;

export const USAGE_CHART_COLORS = {
    cpu: '#465FFF',
    memoria: '#12B76A',
    redRecepcion: '#0BA5EC',
    redEnvio: '#F79009',
};
