import type { FC } from 'react';

import type { StatCardSkeletonProps } from '../props/stat-card-skeleton-props';
import { SkeletonBar } from './skeleton-bar';

/** Silueta de `StatCard`: icono, etiqueta, valor y, si la tarjeta lleva insignia de tendencia, su renglón. */
export const StatCardSkeleton: FC<StatCardSkeletonProps> = ({ conTendencia = false }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
        <SkeletonBar className="size-12 rounded-xl" />
        <SkeletonBar className="mt-5 h-4 w-28" />
        <SkeletonBar className="mt-2 h-6 w-32" />

        {conTendencia && (
            <div className="mt-3 flex items-center gap-2">
                <SkeletonBar className="h-5 w-16 rounded-full" />
                <SkeletonBar className="h-3 w-32" />
            </div>
        )}
    </div>
);
