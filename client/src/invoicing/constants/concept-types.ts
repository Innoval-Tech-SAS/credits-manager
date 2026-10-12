import type { ApiInvoiceConceptType } from '../types/api-invoice';

export const CONCEPT_TYPE_LABELS: Record<ApiInvoiceConceptType, string> = {
    capital: 'Capital',
    interes_corriente: 'Interés corriente',
    interes_mora: 'Interés de mora',
    otros_gastos: 'Otros gastos',
};

export const CONCEPT_TYPE_OPTIONS = (Object.keys(CONCEPT_TYPE_LABELS) as ApiInvoiceConceptType[]).map((valor) => ({
    value: valor,
    label: CONCEPT_TYPE_LABELS[valor],
}));
