import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';

/** Silueta de `StatCard`: icono, etiqueta y valor. */
export const StatCardSkeleton: FC = () => (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
        <SkeletonBar className="size-12 rounded-xl" />
        <SkeletonBar className="mt-5 h-4 w-28" />
        <SkeletonBar className="mt-3 h-7 w-36" />
    </div>
);
