import type { FC } from 'react';

import type { TableSkeletonProps } from '../props/table-skeleton-props';
import { SkeletonBar } from './skeleton-bar';
import { TableSkeletonRows } from './table-skeleton-rows';

/** Silueta de un `DataTable` completo (encabezados y filas) para cuando aún no se conocen sus columnas. */
export const TableSkeleton: FC<TableSkeletonProps> = ({ columns = 5, rows = 8 }) => (
    <div className="overflow-hidden">
        <table className="w-full">
            <thead>
                <tr aria-hidden="true" className="border-b border-gray-100 dark:border-gray-800">
                    {Array.from({ length: columns }, (_, columna) => (
                        <th key={columna} className="px-4 py-3">
                            <SkeletonBar className="h-3 w-16" />
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                <TableSkeletonRows columns={columns} rows={rows} />
            </tbody>
        </table>
    </div>
);
