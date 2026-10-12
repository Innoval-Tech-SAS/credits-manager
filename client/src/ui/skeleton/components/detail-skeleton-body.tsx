import type { FC } from 'react';

import type { SkeletonBodyProps } from '../props/skeleton-body-props';
import { SKELETON_CELL_WIDTHS } from '../constants/skeleton-cell-widths';
import { SkeletonBar } from './skeleton-bar';
import { SkeletonStatus } from './skeleton-status';
import { TableSkeleton } from './table-skeleton';

const DATOS = 6;

/**
 * Contenido de una ficha de detalle mientras cargan sus datos: nombre y estado, datos clave en rejilla y la tabla de líneas.
 * Va dentro de la tarjeta de la página; `DetailPageSkeleton` le pone la tarjeta cuando aún no hay página.
 */
export const DetailSkeletonBody: FC<SkeletonBodyProps> = ({ announce }) => (
    <SkeletonStatus announce={announce}>
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-col gap-2">
                <SkeletonBar className="h-5 w-48" />
                <SkeletonBar className="h-3 w-28" />
            </div>
            <SkeletonBar className="h-6 w-20 rounded-full" />
        </div>

        <div className="mb-8 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: DATOS }, (_, indice) => (
                <div key={indice} className="flex flex-col gap-2">
                    <SkeletonBar className="h-3 w-24" />
                    <SkeletonBar className={`h-4 ${SKELETON_CELL_WIDTHS[indice % SKELETON_CELL_WIDTHS.length]}`} />
                </div>
            ))}
        </div>

        <TableSkeleton rows={4} />
    </SkeletonStatus>
);
