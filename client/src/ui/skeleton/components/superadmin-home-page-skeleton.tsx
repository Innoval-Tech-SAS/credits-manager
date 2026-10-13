import type { FC } from 'react';

import { FieldSkeleton } from './field-skeleton';
import { SkeletonBar } from './skeleton-bar';

const FILTROS = 3;

/** Silueta del inicio de superadmin: filtros (sin asesor ni cliente, el superadmin no los tiene) y el mensaje de "elige una empresa". */
export const SuperadminHomePageSkeleton: FC = () => (
    <div className="flex flex-1 flex-col">
        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4">
                {Array.from({ length: FILTROS }, (_, indice) => (
                    <FieldSkeleton key={indice} />
                ))}
            </div>
        </div>

        <div className="flex items-center justify-center rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <SkeletonBar className="h-4 w-64" />
        </div>
    </div>
);
