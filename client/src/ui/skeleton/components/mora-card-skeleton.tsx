import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';

const RANGOS = 4;

/** Silueta de `DashboardMoraCard`: resumen de mora a la izquierda y el desglose por rango de días a la derecha. */
export const MoraCardSkeleton: FC = () => (
    <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
        <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:items-center md:gap-10">
            <div className="flex flex-col gap-4">
                <SkeletonBar className="size-12 rounded-xl" />

                <div>
                    <SkeletonBar className="h-4 w-20" />
                    <SkeletonBar className="mt-2 h-6 w-28" />
                    <SkeletonBar className="mt-2 h-4 w-32" />
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <SkeletonBar className="h-4 w-36" />
                    <SkeletonBar className="h-4 w-24" />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-x-6 gap-y-5 border-gray-100 md:grid-cols-4 md:border-l md:pl-10 dark:border-gray-800">
                {Array.from({ length: RANGOS }, (_, indice) => (
                    <div key={indice} className="flex flex-col gap-2">
                        <SkeletonBar className="h-3 w-16" />
                        <SkeletonBar className="h-5 w-10" />
                        <SkeletonBar className="h-4 w-20" />
                    </div>
                ))}
            </div>
        </div>
    </section>
);
