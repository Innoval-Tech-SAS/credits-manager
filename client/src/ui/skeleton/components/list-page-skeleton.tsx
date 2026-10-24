import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';
import { TableSkeleton } from './table-skeleton';

/** Silueta de `ModuleListPage`: contador, buscador y botón arriba, tabla con encabezados y paginación abajo. */
export const ListPageSkeleton: FC = () => (
    <div className="flex flex-1 flex-col rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <SkeletonBar className="h-4 w-36" />
            <div className="flex flex-row items-center gap-3">
                <SkeletonBar className="h-11 flex-1 sm:w-72 sm:flex-none" />
                <SkeletonBar className="h-11 w-11 md:w-36" />
            </div>
        </div>

        <TableSkeleton />

        <div className="mt-4 flex items-center justify-between gap-3">
            <SkeletonBar className="h-4 w-28" />
            <div className="flex gap-2">
                {Array.from({ length: 4 }, (_, indice) => (
                    <SkeletonBar key={indice} className="size-10" />
                ))}
            </div>
        </div>
    </div>
);
