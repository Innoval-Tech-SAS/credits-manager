import type { FC } from 'react';

import type { SkeletonBodyProps } from '../props/skeleton-body-props';
import { SkeletonBar } from './skeleton-bar';
import { SkeletonStatus } from './skeleton-status';
import { StatCardSkeleton } from './stat-card-skeleton';

const TARJETAS = 6;
const FILAS = 4;

/**
 * Contenido del home del superadmin mientras llega su resumen: 6 tarjetas, el gráfico de accesos junto a
 * servicios, y la actividad reciente junto a las empresas recientes — misma forma que `SuperadminHome` pinta.
 */
export const SuperadminHomeSkeletonBody: FC<SkeletonBodyProps> = ({ announce }) => (
    <SkeletonStatus announce={announce} className="flex flex-1 flex-col">
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
            {Array.from({ length: TARJETAS }, (_, indice) => (
                <StatCardSkeleton key={indice} />
            ))}
        </div>

        <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-7 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-6 h-5 w-48" />
                <SkeletonBar className="h-72 w-full" />
            </div>
            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-5 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-5 h-5 w-24" />
                <div className="flex flex-col gap-4">
                    {Array.from({ length: FILAS }, (_, indice) => (
                        <SkeletonBar key={indice} className="h-10 w-full" />
                    ))}
                </div>
            </div>
        </div>

        <div className="grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-2">
            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-5 h-5 w-32" />
                <div className="flex flex-col gap-4">
                    {Array.from({ length: FILAS }, (_, indice) => (
                        <SkeletonBar key={indice} className="h-10 w-full" />
                    ))}
                </div>
            </div>
            <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
                <SkeletonBar className="mb-5 h-5 w-36" />
                <div className="flex flex-col gap-4">
                    {Array.from({ length: FILAS }, (_, indice) => (
                        <SkeletonBar key={indice} className="h-10 w-full" />
                    ))}
                </div>
            </div>
        </div>
    </SkeletonStatus>
);
