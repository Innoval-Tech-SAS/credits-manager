import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye } from 'lucide-react';
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
            render: (gasto) => (
                <button
                    type="button"
                    onClick={() => onView(gasto)}
                    className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                >
                    <Eye className="size-5" />
                </button>
            ),
        },
    ];
}
