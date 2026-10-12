import type { FC } from 'react';

import { SKELETON_CELL_WIDTHS } from '../constants/skeleton-cell-widths';
import type { TableSkeletonRowsProps } from '../props/table-skeleton-rows-props';
import { SkeletonBar } from './skeleton-bar';

/** Filas `<tr>` con el mismo borde y relleno que las de `DataTable`, una celda por columna. Van dentro de un `<tbody>`. */
export const TableSkeletonRows: FC<TableSkeletonRowsProps> = ({ columns, rows = 6 }) => (
    <>
        {Array.from({ length: rows }, (_, fila) => (
            <tr key={fila} aria-hidden="true" className="border-b border-gray-100 dark:border-gray-800">
                {Array.from({ length: columns }, (_, columna) => (
                    <td key={columna} className="px-4 py-3">
                        <SkeletonBar className={`h-4 min-w-12 ${SKELETON_CELL_WIDTHS[(fila + columna) % SKELETON_CELL_WIDTHS.length]}`} />
                    </td>
                ))}
            </tr>
        ))}
    </>
);
