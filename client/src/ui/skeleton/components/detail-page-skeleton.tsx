import type { FC } from 'react';

import { DetailSkeletonBody } from './detail-skeleton-body';

/** Silueta de las páginas de detalle de una sola tarjeta (comprobante, gasto, factura, centro de costo…). */
export const DetailPageSkeleton: FC = () => (
    <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <DetailSkeletonBody announce={false} />
    </div>
);
