import type { ApiCredit } from '../../credits/types/api-credit';

/** Raw shape of an entry nested in `ApiPortfolioSummary.al_dia`/`en_mora` (`GET /cartera`). */
export type ApiPortfolioEntry = {
    credito: ApiCredit;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
    dias_mora?: number;
};

export type ApiPortfolioTotal = {
    count: number;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    promedio: string;
};

export type ApiPortfolioAgingBucket = {
    count: number;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
};

/** Raw shape returned by `GET /cartera`. */
export type ApiPortfolioSummary = {
    al_dia: ApiPortfolioEntry[];
    en_mora: ApiPortfolioEntry[];
    totales: {
        al_dia: ApiPortfolioTotal;
        en_mora: ApiPortfolioTotal;
    };
    envejecimiento_mora: {
        '1_30': ApiPortfolioAgingBucket;
        '31_60': ApiPortfolioAgingBucket;
        '61_mas': ApiPortfolioAgingBucket;
    };
};
