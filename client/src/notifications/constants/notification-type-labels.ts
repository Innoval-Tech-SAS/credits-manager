import type { NotificationType } from '../types/api-notification';

export const NOTIFICATION_TYPE_LABELS: Record<NotificationType, string> = {
    exportacion_lista: 'Exportación lista',
    exportacion_fallida: 'Exportación fallida',
    reapertura_solicitada: 'Reapertura solicitada',
    reapertura_autorizada: 'Reapertura autorizada',
    reapertura_rechazada: 'Reapertura rechazada',
    importacion_validada: 'Importación validada',
    importacion_completada: 'Importación completada',
    importacion_fallida: 'Importación fallida',
    extracto_bancario_cargado: 'Extracto bancario cargado',
    extracto_bancario_rechazado: 'Extracto bancario rechazado',
    obligacion_tributaria_por_vencer: 'Obligación tributaria por vencer',
};
