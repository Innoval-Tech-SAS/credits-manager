import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiExpense } from '../types/api-expense';

export function buildExpenseTableColumns(onView: (row: ApiExpense) => void): DataTableColumn<ApiExpense>[] {
    return [
        { key: 'fecha', label: 'Fecha', render: (gasto) => formatDateEs(gasto.fecha) },
        { key: 'numero_documento', label: 'Documento' },
        { key: 'tipo_gasto', label: 'Tipo de gasto', render: (gasto) => gasto.tipo_gasto?.nombre ?? '—' },
        { key: 'tercero', label: 'Tercero', render: (gasto) => gasto.tercero?.nombre ?? '—' },
        { key: 'valor_neto', label: 'Neto a pagar', render: (gasto) => formatMoney(gasto.valor_neto) },
        { key: 'estado', label: 'Estado', render: (gasto) => (gasto.estado === 'causado' ? 'Causado' : 'Anulado') },
        {
            key: 'acciones',
            label: 'Acción',
            render: (gasto) => <ActionButton icon={<Eye className="size-5" />} label="Ver" onClick={() => onView(gasto)} />,
        },
    ];
}
