import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';

const FILAS = 6;

/** Lo que se ve mientras llega el código de una página: la silueta de la tarjeta de un listado (contador, acciones y filas). */
export const PageSkeleton: FC = () => (
    <div role="status" aria-busy="true" className="flex flex-col pb-6">
        <span className="sr-only">Cargando…</span>

        <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <SkeletonBar className="h-4 w-32" />
                <div className="flex flex-col gap-3 sm:flex-row">
                    <SkeletonBar className="h-11 w-full sm:w-72" />
                    <SkeletonBar className="h-11 w-full sm:w-36" />
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <SkeletonBar className="h-10 w-full" />
                {Array.from({ length: FILAS }, (_, indice) => (
                    <SkeletonBar key={indice} className="h-8 w-full" />
                ))}
            </div>
        </div>
    </div>
);
