export type NotificationType =
    | 'exportacion_lista'
    | 'exportacion_fallida'
    | 'reapertura_solicitada'
    | 'reapertura_autorizada'
    | 'reapertura_rechazada';

/** Raw shape returned by `GET /notificaciones` (rows), `POST /notificaciones/{id}/leer` and pushed by the `.notificacion.creada` WebSocket event. */
export type ApiNotification = {
    id: number;
    empresa_id: number | null;
    usuario_id: number;
    tipo: NotificationType;
    titulo: string;
    mensaje: string;
    datos: { exportacion_id?: number; formato?: string; solicitud_id?: number; periodo_id?: number } | null;
    leida_en: string | null;
    creado_en: string | null;
};
