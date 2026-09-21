/** Raw shape returned by `GET /contabilidad/solicitudes-reapertura` y por los endpoints de solicitar, autorizar y rechazar. */
export type ApiReopenRequest = {
    id: number;
    periodo_id: number;
    motivo: string;
    estado: 'pendiente' | 'autorizada' | 'rechazada';
    motivo_resolucion: string | null;
    resuelto_en: string | null;
    creado_en: string | null;
    periodo?: { id: number; anio: number; mes: number; estado: 'abierto' | 'cerrado' };
    solicitante?: { id: number; nombre: string } | null;
    resolutor?: { id: number; nombre: string } | null;
};
