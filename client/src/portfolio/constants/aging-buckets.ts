import type { AgingBucketKey } from '../types/aging-bucket-key';

/** Rangos de altura de mora, del más reciente al más antiguo. */
export const AGING_BUCKETS: { key: AgingBucketKey; label: string }[] = [
    { key: '1_30', label: '1-30 días' },
    { key: '31_60', label: '31-60 días' },
    { key: '61_90', label: '61-90 días' },
    { key: 'mas_90', label: 'Más de 90 días' },
];
