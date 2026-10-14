export type ResultadoEjecucionTarea = 'ok' | 'error' | 'omitida';

export type ApiTareaProgramada = {
    clave: string;
    /** `HH:MM`. */
    hora: string;
    activa: boolean;
    /** `respaldos:ejecutar` no admite pausa. */
    pausable: boolean;
    ultima_ejecucion: { resultado: ResultadoEjecucionTarea; finalizado_en: string } | null;
};

export type ApiEjecucionTareaProgramada = {
    id: number;
    tarea_clave: string;
    iniciado_en: string;
    finalizado_en: string;
    resultado: ResultadoEjecucionTarea;
    error: string | null;
};
