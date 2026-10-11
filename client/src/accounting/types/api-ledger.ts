import type { ApiAccount } from './api-account';

/** Raw shape of one row in `ApiLedger.movimientos` (`GET /contabilidad/libro-mayor`). */
export type ApiLedgerMovement = {
    fecha: string;
    consecutivo: number;
    tipo: string;
    /** Número visible ya formateado (incluye el ciclo del reinicio periódico cuando aplica). */
    numero: string;
    descripcion: string | null;
    debito: string;
    credito: string;
    tercero: string | null;
};

/** Raw shape returned by `GET /contabilidad/libro-mayor`. */
export type ApiLedger = {
    cuenta: ApiAccount;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo_inicial: string;
    movimientos: ApiLedgerMovement[];
};
