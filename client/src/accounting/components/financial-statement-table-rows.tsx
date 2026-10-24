import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import type { FinancialStatementTableRowsProps } from '../props/financial-statement-table-rows-props';

export const FinancialStatementTableRows: FC<FinancialStatementTableRowsProps> = ({ filas, negativo }) => (
    <>
        {filas.map((fila) => (
            <tr key={fila.codigo} className="border-b border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                    {fila.codigo} {fila.nombre}
                </td>
                <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                    {negativo ? `(${formatMoney(fila.valor)})` : formatMoney(fila.valor)}
                </td>
            </tr>
        ))}
    </>
);
