import type { FC } from 'react';

import { FieldSkeleton } from './field-skeleton';
import { SkeletonBar } from './skeleton-bar';
import { TableSkeleton } from './table-skeleton';

const CAMPOS = 2;

/** Silueta de los informes (libros, balances, auxiliares, cuentas por pagar/cobrar…): tarjeta de filtros y tarjeta de resultados. */
export const ReportPageSkeleton: FC = () => (
    <div className="flex flex-1 flex-col gap-6">
        <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                {Array.from({ length: CAMPOS }, (_, indice) => (
                    <FieldSkeleton key={indice} />
                ))}
                <div className="flex items-end">
                    <SkeletonBar className="h-11 w-full sm:w-36" />
                </div>
            </div>
        </div>

        <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <TableSkeleton columns={6} />
        </div>
    </div>
);
