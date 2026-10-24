import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { Eye, X } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import { formatMoney } from '../../shared/utils/format-money';
import { CREDIT_STATUS_BADGE_CLASSES } from '../constants/credit-status-badge-classes';
import { CREDIT_STATUS_LABEL } from '../constants/credit-status-label';
import type { BuildCreditTableColumnsOptions } from '../types/build-credit-table-columns-options';
import type { ApiCredit } from '../types/api-credit';
import { puedeCancelarCredito } from './can-cancel-credit';

export function buildCreditTableColumns({ canCancel, onView, onCancel }: BuildCreditTableColumnsOptions): DataTableColumn<ApiCredit>[] {
    return [
        { key: 'cliente', label: 'Cliente', render: (row) => row.cliente?.tercero.nombre ?? '—' },
        {
            key: 'tipo_credito',
            label: 'Origen',
            render: (row) =>
                row.tipo_credito === 'refinanciacion' ? (
                    <span className="rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">
                        Refinanciación
                    </span>
                ) : (
                    <span className="text-gray-500 dark:text-gray-400">Nuevo</span>
                ),
        },
        { key: 'valor_prestamo', label: 'Valor', render: (row) => formatMoney(row.valor_prestamo) },
        { key: 'tasa_interes', label: 'Tasa', render: (row) => `${parseFloat(row.tasa_interes).toFixed(3)}%` },
        { key: 'plazo', label: 'Plazo' },
        { key: 'tipo_pago', label: 'Tipo pago', render: (row) => (row.tipo_pago === 'mensual' ? 'Mensual' : 'Quincenal') },
        { key: 'valor_cuota_total', label: 'Cuota', render: (row) => formatMoney(row.valor_cuota_total) },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) => (
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${CREDIT_STATUS_BADGE_CLASSES[row.estado]}`}>
                    {CREDIT_STATUS_LABEL[row.estado]}
                </span>
            ),
        },
        {
            key: 'cartera',
            label: 'Cartera',
            render: (row) => {
                const enMora = (row.cuotas_vencidas ?? 0) > 0;
                return (
                    <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                            enMora
                                ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                        }`}
                    >
                        {enMora ? 'En mora' : 'Al día'}
                    </span>
                );
            },
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (row) => (
                <div className="flex w-full items-center gap-3">
                    <ActionButton icon={<Eye className="size-5" />} label="Ver" onClick={() => onView(row)} />

                    {canCancel && puedeCancelarCredito(row, (row.pagos_count ?? 0) > 0) && (
                        <ActionButton icon={<X className="size-5" />} label="Cancelar" variant="danger" onClick={() => onCancel(row)} />
                    )}
                </div>
            ),
        },
    ];
}
