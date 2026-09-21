import type { CreditStatus } from './credit-status';

/** Bloque `refinanciacion` de `GET /creditos/{id}` y `GET /creditos/{id}/estado-cuenta`; nulo si el crédito no interviene en una refinanciación. */
export type ApiRefinancingLink = {
    /** `refinancia`: este crédito refinancia a otro; `refinanciado`: este crédito fue reemplazado por otro. */
    tipo: 'refinancia' | 'refinanciado';
    credito_relacionado: {
        id: number;
        estado: CreditStatus;
        /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
        valor_prestamo: string;
        fecha_desembolso: string;
    };
    /** Solo con `tipo` = `refinancia`; decimal string, nulo en refinanciaciones anteriores a la columna `saldo_refinanciado`. */
    saldo_tomado?: string | null;
    /** Solo con `tipo` = `refinancia`; decimal string, nulo en refinanciaciones anteriores a la columna `saldo_refinanciado`. */
    valor_adicional?: string | null;
};
