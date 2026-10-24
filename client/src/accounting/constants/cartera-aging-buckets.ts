import type { ApiCarteraEdades } from '../types/api-cartera-document';

/** A diferencia de `portfolio/constants/aging-buckets.ts` (cartera de créditos), aquí sí hay un tramo "corriente": un documento de CxP/CxC puede no estar vencido todavía. */
export const CARTERA_AGING_BUCKETS: { key: keyof ApiCarteraEdades; label: string }[] = [
    { key: 'corriente', label: 'Corriente' },
    { key: '1_30', label: '1-30 días' },
    { key: '31_60', label: '31-60 días' },
    { key: '61_90', label: '61-90 días' },
    { key: 'mas_90', label: 'Más de 90 días' },
];
