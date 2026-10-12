import type { FC } from 'react';

import { SkeletonBar } from './skeleton-bar';

const LEYENDA = 4;
const DIAS_SEMANA = 7;
const SEMANAS = 6;

/** Silueta del calendario: leyenda de colores y la vista de mes (barra de navegación y cuadrícula de 6 semanas) con su alto fijo de 650 px. */
export const CalendarPageSkeleton: FC = () => (
    <div className="flex flex-col">
        <div className="mb-4 flex flex-wrap items-center gap-4">
            {Array.from({ length: LEYENDA }, (_, indice) => (
                <div key={indice} className="flex items-center gap-2">
                    <SkeletonBar className="size-2.5 rounded-full" />
                    <SkeletonBar className="h-4 w-24" />
                </div>
            ))}
        </div>

        <div className="flex h-162.5 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-center justify-between gap-3 p-4">
                <SkeletonBar className="h-10 w-24" />
                <SkeletonBar className="h-6 w-40" />
                <SkeletonBar className="hidden h-10 w-56 sm:block" />
            </div>

            <div className="grid grid-cols-7 border-t border-gray-100 dark:border-gray-800">
                {Array.from({ length: DIAS_SEMANA }, (_, indice) => (
                    <div key={indice} className="flex justify-center py-3">
                        <SkeletonBar className="h-3 w-8" />
                    </div>
                ))}
            </div>

            <div className="grid flex-1 grid-cols-7 grid-rows-6">
                {Array.from({ length: DIAS_SEMANA * SEMANAS }, (_, indice) => (
                    <div key={indice} className="flex flex-col items-end gap-2 border-t border-l border-gray-100 p-2 nth-[7n+1]:border-l-0 dark:border-gray-800">
                        <SkeletonBar className="h-3 w-4" />
                        {indice % 5 === 2 && <SkeletonBar className="h-3 w-full" />}
                    </div>
                ))}
            </div>
        </div>
    </div>
);
