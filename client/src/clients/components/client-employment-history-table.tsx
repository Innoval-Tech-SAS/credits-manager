import type { FC } from 'react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { DataTable } from '@ui/table/components/data-table';
import type { ClientEmploymentHistoryTableProps } from '../props/client-employment-history-table-props';

export const ClientEmploymentHistoryTable: FC<ClientEmploymentHistoryTableProps> = ({ historial }) => (
    <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Historial de empleadores</h2>
        <DataTable
            columns={[
                { key: 'empleador', label: 'Empleador', render: (row) => row.empleador.nombre },
                { key: 'fecha_inicio', label: 'Desde', render: (row) => formatDateEs(row.fecha_inicio) },
                { key: 'fecha_fin', label: 'Hasta', render: (row) => (row.fecha_fin ? formatDateEs(row.fecha_fin) : '—') },
                {
                    key: 'vigente',
                    label: 'Estado',
                    render: (row) => (
                        <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                row.vigente
                                    ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                            }`}
                        >
                            {row.vigente ? 'Vigente' : 'Finalizado'}
                        </span>
                    ),
                },
            ]}
            rows={historial}
            getRowKey={(row) => row.id}
            emptyMessage="Este cliente aún no tiene empleadores registrados."
        />
    </div>
);
