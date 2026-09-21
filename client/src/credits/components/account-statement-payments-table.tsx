import type { FC } from 'react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { AccountStatementPaymentsTableProps } from '../props/account-statement-payments-table-props';

export const AccountStatementPaymentsTable: FC<AccountStatementPaymentsTableProps> = ({ pagos, totalPagado }) => (
    <>
        <h3 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Detalle de pagos</h3>
        <div className="custom-scrollbar mb-6 overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <th className="py-2 pr-4 font-medium whitespace-nowrap">Fecha</th>
                        <th className="py-2 pr-4 font-medium whitespace-nowrap">Concepto</th>
                        <th className="py-2 pr-4 font-medium whitespace-nowrap">Valor</th>
                        <th className="py-2 pr-4 font-medium whitespace-nowrap">Forma de pago</th>
                    </tr>
                </thead>
                <tbody>
                    {pagos.length === 0 ? (
                        <tr>
                            <td colSpan={4} className="py-4 text-center text-gray-500 dark:text-gray-400">
                                Aún no se han registrado pagos.
                            </td>
                        </tr>
                    ) : (
                        pagos.map((pago) => (
                            <tr key={pago.id} className="border-b border-gray-100 text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                <td className="py-2 pr-4 whitespace-nowrap">{formatDateEs(pago.fecha_pago)}</td>
                                <td className="min-w-28 py-2 pr-4">{pago.observacion || 'Pago'}</td>
                                <td className="py-2 pr-4 whitespace-nowrap">{formatMoney(pago.valor_pago)}</td>
                                <td className="py-2 pr-4 whitespace-nowrap capitalize">{pago.forma_pago}</td>
                            </tr>
                        ))
                    )}
                    <tr className="border-t-2 border-gray-800 font-semibold text-gray-800 dark:border-white/90 dark:text-white/90">
                        <td className="py-2 pr-4" colSpan={2}>
                            Total pagado
                        </td>
                        <td className="py-2 pr-4 whitespace-nowrap">{formatMoney(totalPagado)}</td>
                        <td className="py-2" />
                    </tr>
                </tbody>
            </table>
        </div>
    </>
);
