import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Button } from '@ui/button/components/button';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ReceiptDownloadButton } from '../components/receipt-download-button';
import type { ApiPaymentListItem } from '../types/api-payment-list-item';

type BuildPaymentTableColumnsOptions = {
    onViewCredito: (creditoId: number) => void;
    /** Nulo cuando el usuario no tiene el permiso de reversa: la columna de acciones no se muestra. */
    onReversar: ((pago: ApiPaymentListItem) => void) | null;
};

export function buildPaymentTableColumns({ onViewCredito, onReversar }: BuildPaymentTableColumnsOptions): DataTableColumn<ApiPaymentListItem>[] {
    const columns: DataTableColumn<ApiPaymentListItem>[] = [
        { key: 'fecha_pago', label: 'Fecha', render: (row) => formatDateEs(row.fecha_pago) },
        { key: 'cliente', label: 'Cliente', render: (row) => row.cliente?.tercero.nombre ?? '—' },
        {
            key: 'credito_id',
            label: 'Crédito',
            render: (row) => (
                <button type="button" onClick={() => onViewCredito(row.credito_id)} className="text-brand-500 hover:underline dark:text-brand-400">
                    #{row.credito_id}
                </button>
            ),
        },
        { key: 'valor_pago', label: 'Valor', render: (row) => formatMoney(row.valor_pago) },
        { key: 'valor_capital', label: 'Capital', render: (row) => (row.valor_capital === null ? '—' : formatMoney(row.valor_capital)) },
        { key: 'valor_interes', label: 'Intereses', render: (row) => (row.valor_interes === null ? '—' : formatMoney(row.valor_interes)) },
        { key: 'valor_otros_gastos', label: 'Otros gastos', render: (row) => (row.valor_otros_gastos === null ? '—' : formatMoney(row.valor_otros_gastos)) },
        { key: 'forma_pago', label: 'Forma de pago', render: (row) => <span className="capitalize">{row.forma_pago}</span> },
        { key: 'observacion', label: 'Observación', render: (row) => row.observacion ?? '' },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) =>
                row.reversa ? (
                    <div className="flex flex-col gap-0.5">
                        <span className="inline-block w-fit rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-error-600 dark:bg-error-500/15 dark:text-error-500">
                            Reversado
                        </span>
                        <span className="text-xs text-gray-500 dark:text-gray-400">{row.reversa.motivo}</span>
                    </div>
                ) : (
                    <span className="inline-block rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-success-600 dark:bg-success-500/15 dark:text-success-500">
                        Vigente
                    </span>
                ),
        },
        { key: 'recibo', label: 'Recibo', render: (row) => <ReceiptDownloadButton payment={row} /> },
    ];

    if (onReversar) {
        columns.push({
            key: 'acciones',
            label: '',
            render: (row) =>
                row.reversa ? null : (
                    <Button variant="outline" size="sm" onClick={() => onReversar(row)}>
                        Reversar
                    </Button>
                ),
        });
    }

    return columns;
}
