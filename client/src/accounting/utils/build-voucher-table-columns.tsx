import { Link } from 'react-router';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { DataTableColumn } from '@ui/table/props/data-table-props';
import type { ApiVoucher } from '../types/api-voucher';
import { VOUCHER_STATUS_BADGE_CLASSES } from '../constants/voucher-status-badge-classes';

export function buildVoucherTableColumns(): DataTableColumn<ApiVoucher>[] {
    return [
        { key: 'tipo_comprobante', label: 'Tipo', render: (row) => row.tipo_comprobante?.codigo ?? '—' },
        { key: 'consecutivo', label: 'Consec.' },
        { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
        { key: 'concepto', label: 'Concepto', render: (row) => row.concepto ?? '—' },
        { key: 'total_debito', label: 'Valor', render: (row) => formatMoney(row.total_debito) },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) => (
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${VOUCHER_STATUS_BADGE_CLASSES[row.estado]}`}>
                    {row.estado}
                </span>
            ),
        },
        {
            key: 'acciones',
            label: '',
            render: (row) => (
                <Link to={`/contabilidad/comprobantes/${row.id}`} className="text-brand-600 hover:underline dark:text-brand-400">
                    Ver
                </Link>
            ),
        },
    ];
}
