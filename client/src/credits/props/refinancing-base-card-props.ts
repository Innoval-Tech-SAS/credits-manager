export type RefinancingBaseCardProps = {
    /** Decimal string; nulo mientras no hay crédito original elegido. */
    saldoTomado: string | null;
    /** Lo que el usuario digitó como nuevo valor a prestar (puede estar vacío). */
    valorAdicional: string;
    isLoading: boolean;
};
