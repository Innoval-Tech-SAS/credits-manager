import type { ResultadoEjecucionTarea } from './api-tarea-programada';
import type { ServiceStatus } from './service-status';

export type ApiServerMetrics = {
    cpu: { porcentaje: number; nucleos: number } | null;
    memoria: { total_bytes: number; usada_bytes: number; porcentaje: number; swap_total_bytes: number; swap_usada_bytes: number } | null;
    /** Carga promedio a 1, 5 y 15 minutos. */
    carga: number[] | null;
    disco: { total_bytes: number; usado_bytes: number; porcentaje: number } | null;
    /** Contador acumulado desde que la interfaz arrancó (no una tasa), sumando todas las interfaces salvo loopback. */
    red: { rx_bytes: number; tx_bytes: number } | null;
    encendido_segundos: number | null;
};

export type ApiServiceState = {
    servicio: string;
    estado: ServiceStatus;
    detalle: string | null;
    ultimo_latido: string | null;
};

export type ApiQueueState = {
    cola: string;
    pendientes: number | null;
    programados: number | null;
    en_proceso: number | null;
    fallidos: number | null;
};

/** Raw shape returned by `GET /monitoreo/estado` and pushed by Reverb as `estado.actualizado` on the private `monitoreo` channel. */
export type ApiSystemHealth = {
    servidor: ApiServerMetrics;
    servicios: ApiServiceState[];
    colas: ApiQueueState[];
    auditoria: { en_stream: number | null; pendientes: number | null };
    /** Solo trae las claves con al menos una ejecución; las que nunca han corrido no aparecen. */
    tareas_programadas: Partial<Record<string, { resultado: ResultadoEjecucionTarea; finalizado_en: string }>>;
    generado_en: string;
};
