/** Una muestra del historial que la página acumula en memoria para la gráfica de uso. */
export type UsageSample = {
    hora: string;
    /** Epoch en milisegundos: para calcular bytes/segundo de red entre dos muestras consecutivas. */
    timestamp: number;
    cpu: number;
    memoria: number;
    red: { rx_bytes: number; tx_bytes: number } | null;
};
