import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiPaymentListItem } from '../types/api-payment-list-item';

export function buildPaymentTableColumns(onViewCredito: (creditoId: number) => void): DataTableColumn<ApiPaymentListItem>[] {
    return [
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
        { key: 'forma_pago', label: 'Forma de pago', render: (row) => <span className="capitalize">{row.forma_pago}</span> },
        { key: 'observacion', label: 'Observación', render: (row) => row.observacion ?? '' },
    ];
}
