/** Raw shape returned inside `reglas` by `GET /contabilidad/reglas` and by `POST`. */
export type ApiPostingRule = {
    id: number;
    evento: string;
    cuenta_debito_id: number;
    cuenta_credito_id: number;
    tipo_comprobante_id: number;
    activo: boolean;
};

/** Raw shape of `GET /contabilidad/reglas`. */
export type ApiPostingRulesResponse = {
    eventos: string[];
    reglas: Record<string, ApiPostingRule>;
};
