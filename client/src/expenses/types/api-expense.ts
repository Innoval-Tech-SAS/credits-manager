import type { ApiFiscalRuleApplied } from './api-fiscal-rule-applied';

/** Raw shape of a row returned by `GET /gastos` (paginated `data`), `POST /gastos` and `GET /gastos/{id}`. */
export type ApiExpense = {
    id: number;
    tipo_gasto_id: number;
    tercero_id: number | null;
    municipio_id: number | null;
    fecha: string;
    numero_documento: string;
    concepto: string;
    valor_bruto: string;
    valor_iva: string;
    valor_retefuente: string;
    valor_reteica: string;
    valor_reteiva: string;
    valor_neto: string;
    parametrizacion_tipo_gasto_id: number;
    comprobante_id: number | null;
    documento_tercero_id: number | null;
    estado: 'causado' | 'anulado';
    tipo_gasto?: { id: number; codigo: string; nombre: string };
    tercero?: { id: number; nombre: string } | null;
    reglas_fiscales_aplicadas?: ApiFiscalRuleApplied[];
};
