import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ReceiptPdfButton } from '../components/receipt-pdf-button';
import type { ApiReceipt } from '../types/api-receipt';

export function buildReceiptTableColumns(): DataTableColumn<ApiReceipt>[] {
    return [
        {
            key: 'numero',
            label: 'Recibo',
            render: (row) => <ReceiptPdfButton pagoId={row.pago.id} numero={row.numero} anulado={row.estado === 'anulado'} />,
        },
        { key: 'fecha_pago', label: 'Fecha de pago', render: (row) => formatDateEs(row.pago.fecha_pago) },
        { key: 'cliente', label: 'Tercero', render: (row) => row.pago.cliente.tercero.nombre },
        {
            key: 'credito',
            label: 'Crédito',
            render: (row) => (row.pago.credito ? (row.pago.credito.codigo ?? `#${row.pago.credito.id}`) : '—'),
        },
        { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.pago.valor_pago) },
        { key: 'forma_pago', label: 'Forma de pago', render: (row) => <span className="capitalize">{row.pago.forma_pago}</span> },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) =>
                row.estado === 'anulado' ? (
                    <div className="flex flex-col gap-0.5">
                        <span className="inline-block w-fit rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-error-600 dark:bg-error-500/15 dark:text-error-500">
                            Anulado
                        </span>
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            {row.motivo_anulacion}
                            {row.anulado_en ? ` · ${formatDateEs(row.anulado_en)}` : ''}
                        </span>
                    </div>
                ) : (
                    <span className="inline-block rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-success-600 dark:bg-success-500/15 dark:text-success-500">
                        Emitido
                    </span>
                ),
        },
    ];
}
