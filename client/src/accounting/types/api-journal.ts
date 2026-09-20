/** Raw shape of one row in `ApiJournal.movimientos` (`GET /contabilidad/libro-diario`). */
export type ApiJournalEntry = {
    fecha: string;
    consecutivo: number;
    tipo: string;
    cuenta_codigo: string;
    cuenta_nombre: string;
    descripcion: string | null;
    debito: string;
    credito: string;
};

/** Raw shape returned by `GET /contabilidad/libro-diario`. */
export type ApiJournal = {
    movimientos: ApiJournalEntry[];
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    total_debito: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    total_credito: string;
};
