import type { FC } from 'react';

import { DashboardSkeletonBody } from './dashboard-skeleton-body';
import { FieldSkeleton } from './field-skeleton';

const FILTROS = 4;

/** Silueta del inicio completo: la tarjeta de filtros y debajo el contenido del resumen. */
export const DashboardPageSkeleton: FC = () => (
    <div className="flex flex-1 flex-col">
        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4">
                {Array.from({ length: FILTROS }, (_, indice) => (
                    <FieldSkeleton key={indice} />
                ))}
            </div>
        </div>

        <DashboardSkeletonBody announce={false} />
    </div>
);
