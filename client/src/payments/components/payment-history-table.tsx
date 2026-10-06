import type { FC } from 'react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { DataTable } from '@ui/table/components/data-table';
import type { DataTableColumn } from '@ui/table/props/data-table-props';
import type { PaymentHistoryTableProps } from '../props/payment-history-table-props';
import { ReceiptDownloadButton } from './receipt-download-button';
import type { ApiPayment } from '../types/api-payment';

export const PaymentHistoryTable: FC<PaymentHistoryTableProps> = ({ pagos, showCredito = false, onViewCredito }) => {
    const columns: DataTableColumn<ApiPayment>[] = [
        { key: 'fecha_pago', label: 'Fecha', render: (row) => formatDateEs(row.fecha_pago) },
        ...(showCredito
            ? [
                  {
                      key: 'credito_id',
                      label: 'Crédito',
                      render: (row: ApiPayment) => (
                          <button
                              type="button"
                              onClick={() => onViewCredito?.(row.credito_id)}
                              className="text-brand-500 hover:underline dark:text-brand-400"
                          >
                              #{row.credito_id}
                          </button>
                      ),
                  } satisfies DataTableColumn<ApiPayment>,
              ]
            : []),
        { key: 'valor_pago', label: 'Valor', render: (row) => formatMoney(row.valor_pago) },
        { key: 'valor_capital', label: 'Capital', render: (row) => (row.valor_capital === null ? '—' : formatMoney(row.valor_capital)) },
        { key: 'valor_interes', label: 'Intereses', render: (row) => (row.valor_interes === null ? '—' : formatMoney(row.valor_interes)) },
        { key: 'valor_otros_gastos', label: 'Otros gastos', render: (row) => (row.valor_otros_gastos === null ? '—' : formatMoney(row.valor_otros_gastos)) },
        { key: 'forma_pago', label: 'Forma de pago', render: (row) => <span className="capitalize">{row.forma_pago}</span> },
        { key: 'observacion', label: 'Observación', render: (row) => row.observacion ?? '' },
        { key: 'recibo', label: 'Recibo', render: (row) => <ReceiptDownloadButton payment={row} /> },
    ];

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Historial de pagos</h2>
            <DataTable columns={columns} rows={pagos} getRowKey={(row) => row.id} emptyMessage="Aún no se han registrado pagos." />
        </div>
    );
};
