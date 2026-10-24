/** Raw shape of one account row inside `ApiFinancialStatements` (`GET /contabilidad/estados-financieros`). */
export type ApiFinancialStatementRow = {
    codigo: string;
    nombre: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    valor: string;
};

/** Raw shape returned by `GET /contabilidad/estados-financieros`. */
export type ApiFinancialStatements = {
    estado_resultados: {
        ingresos: ApiFinancialStatementRow[];
        gastos_costos: ApiFinancialStatementRow[];
        total_ingresos: string;
        total_gastos: string;
        utilidad: string;
    };
    situacion_financiera: {
        activos: ApiFinancialStatementRow[];
        pasivos: ApiFinancialStatementRow[];
        patrimonio: ApiFinancialStatementRow[];
        total_activos: string;
        total_pasivos: string;
        total_patrimonio: string;
        utilidad: string;
        cuadra: boolean;
    };
};
