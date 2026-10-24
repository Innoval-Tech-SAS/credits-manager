/** Raw shape of one row in `ApiTrialBalance.saldos` (`GET /contabilidad/balance-prueba`). */
export type ApiTrialBalanceRow = {
    codigo: string;
    nombre: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    debitos: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    creditos: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
};

/** Raw shape returned by `GET /contabilidad/balance-prueba`. */
export type ApiTrialBalance = {
    saldos: ApiTrialBalanceRow[];
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    total_debito: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    total_credito: string;
};
