/** Raw shape returned by `GET /contabilidad/periodos`, `.../cerrar` y `.../reabrir`. */
export type ApiPeriod = {
    id: number;
    anio: number;
    mes: number;
    estado: 'abierto' | 'cerrado';
    cerrado_en: string | null;
    reabierto_motivo: string | null;
};
