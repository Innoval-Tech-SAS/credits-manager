import type { FC } from 'react';

import { SKELETON_CELL_WIDTHS } from '../constants/skeleton-cell-widths';
import { SkeletonBar } from './skeleton-bar';
import { StatCardSkeleton } from './stat-card-skeleton';
import { TableSkeleton } from './table-skeleton';

const TARJETAS = 4;
const DATOS = 6;

/** Silueta de las fichas con resumen (crédito, cliente, pagos de un crédito, cartera, metas): tarjetas de métricas, datos y tabla. */
export const SummaryPageSkeleton: FC = () => (
    <div className="flex flex-1 flex-col">
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: TARJETAS }, (_, indice) => (
                <StatCardSkeleton key={indice} />
            ))}
        </div>

        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <SkeletonBar className="mb-6 h-5 w-40" />
            <div className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                {Array.from({ length: DATOS }, (_, indice) => (
                    <div key={indice} className="flex flex-col gap-2">
                        <SkeletonBar className="h-3 w-24" />
                        <SkeletonBar className={`h-4 ${SKELETON_CELL_WIDTHS[indice % SKELETON_CELL_WIDTHS.length]}`} />
                    </div>
                ))}
            </div>
        </div>

        <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <SkeletonBar className="mb-6 h-5 w-40" />
            <TableSkeleton rows={5} />
        </div>
    </div>
);
