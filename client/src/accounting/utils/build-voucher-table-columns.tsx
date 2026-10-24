import { Link } from 'react-router';
import { Eye } from 'lucide-react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Tooltip } from '@ui/tooltip/components/tooltip';
import { ACTION_BUTTON_VARIANT_CLASSES } from '@ui/action-button/constants/action-button-styles';
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
                <Tooltip label="Ver">
                    <Link to={`/contabilidad/comprobantes/${row.id}`} aria-label="Ver" className={ACTION_BUTTON_VARIANT_CLASSES.default}>
                        <Eye className="size-5" />
                    </Link>
                </Tooltip>
            ),
        },
    ];
}
