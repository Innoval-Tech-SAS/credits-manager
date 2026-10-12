import type { FC } from 'react';

import { SkeletonBar } from '@ui/skeleton/components/skeleton-bar';

/** Silueta de `GoalProgressCard`: tipo y período, ejecutado con su porcentaje, barra de avance y brecha. */
export const GoalProgressCardSkeleton: FC = () => (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
        <SkeletonBar className="h-6 w-32" />
        <SkeletonBar className="mt-2 h-3 w-24" />

        <div className="mt-5 flex items-end justify-between gap-3">
            <div className="flex flex-col gap-2">
                <SkeletonBar className="h-4 w-20" />
                <SkeletonBar className="h-7 w-40" />
            </div>
            <SkeletonBar className="h-8 w-14" />
        </div>

        <SkeletonBar className="mt-4 h-2.5 w-full rounded-full" />
        <SkeletonBar className="mt-3 h-3 w-36" />
    </div>
);
