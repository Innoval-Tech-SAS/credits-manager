/** Raw shape of a row inside `GET /contabilidad/migraciones-apertura`'s paginated `data`, and of `POST`. */
export type ApiOpeningBalanceImport = {
    id: number;
    tipo: 'plan_cuentas' | 'terceros' | 'saldos_iniciales';
    archivo_original: string;
    registros_aceptados: number;
    registros_rechazados: number;
    detalle_errores: string[] | null;
    creado_en: string;
};
