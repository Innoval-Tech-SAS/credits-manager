import type { ApiNotification } from '../types/api-notification';

/** `exportacion_lista`/`exportacion_fallida` no navegan (la primera dispara una descarga aparte); la segunda es informativa). */
export function buildNotificationLink(notificacion: ApiNotification): string | null {
    const datos = notificacion.datos;

    switch (notificacion.tipo) {
        case 'reapertura_solicitada':
        case 'reapertura_autorizada':
        case 'reapertura_rechazada':
            return '/contabilidad/periodos';
        case 'importacion_validada':
        case 'importacion_completada':
        case 'importacion_fallida':
            return datos?.importacion_id !== undefined ? `/importaciones/${datos.importacion_id}` : null;
        case 'extracto_bancario_cargado':
        case 'extracto_bancario_rechazado':
            return datos?.extracto_bancario_id !== undefined ? `/conciliacion/extractos/${datos.extracto_bancario_id}/conciliar` : null;
        case 'obligacion_tributaria_por_vencer':
            return '/obligaciones-tributarias';
        default:
            return null;
    }
}
