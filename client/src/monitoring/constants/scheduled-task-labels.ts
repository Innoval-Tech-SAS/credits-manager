import type { ResultadoEjecucionTarea } from '../types/api-tarea-programada';

/** Nombre legible de cada clave del catálogo fijo (ver `TareaProgramadaConstants::CLAVES` en el backend). */
export const SCHEDULED_TASK_LABELS: Record<string, string> = {
    'cuotas:actualizar-vencidas': 'Actualizar cuotas vencidas',
    'respaldos:ejecutar': 'Respaldo automático',
    'exportaciones:limpiar': 'Limpiar exportaciones',
    'importaciones:limpiar': 'Limpiar importaciones',
    'metas:evaluar': 'Evaluar metas',
    'obligaciones_tributarias:evaluar-alertas': 'Alertas tributarias',
};

export const SCHEDULED_TASK_RESULT_LABELS: Record<ResultadoEjecucionTarea, string> = {
    ok: 'Exitosa',
    error: 'Error',
    omitida: 'Omitida',
};

export const SCHEDULED_TASK_RESULT_CLASSES: Record<ResultadoEjecucionTarea, string> = {
    ok: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
    error: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
    omitida: 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
};
