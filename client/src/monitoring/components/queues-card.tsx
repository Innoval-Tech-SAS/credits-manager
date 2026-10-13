import type { FC } from 'react';

import { DataTable } from '@ui/table/components/data-table';
import { QUEUE_LABELS } from '../constants/queue-labels';
import type { QueuesCardProps } from '../props/queues-card-props';

function formatCount(value: number | null): string {
    return value === null ? '—' : value.toLocaleString('es-CO');
}

export const QueuesCard: FC<QueuesCardProps> = ({ colas, auditoria }) => {
    const sinProcesar = auditoria.en_stream === null || auditoria.pendientes === null ? null : auditoria.en_stream - auditoria.pendientes;

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Colas</h2>

            <DataTable
                columns={[
                    { key: 'cola', label: 'Cola', render: (row) => QUEUE_LABELS[row.cola] ?? row.cola },
                    { key: 'pendientes', label: 'En espera', render: (row) => formatCount(row.pendientes) },
                    { key: 'en_proceso', label: 'Procesando', render: (row) => formatCount(row.en_proceso) },
                    { key: 'programados', label: 'Programados', render: (row) => formatCount(row.programados) },
                    {
                        key: 'fallidos',
                        label: 'Fallidos',
                        render: (row) => (
                            <span className={row.fallidos ? 'font-medium text-error-600 dark:text-error-400' : ''}>{formatCount(row.fallidos)}</span>
                        ),
                    },
                ]}
                rows={colas}
                getRowKey={(row) => row.cola}
                emptyMessage="No hay colas configuradas."
            />

            <div className="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/3">
                <p className="font-medium text-gray-800 dark:text-white/90">Bitácora de auditoría</p>
                <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                    {sinProcesar === null
                        ? 'No se pudo leer el stream de Redis.'
                        : `${formatCount(sinProcesar)} evento(s) esperando al listener · ${formatCount(auditoria.pendientes)} entregado(s) sin confirmar.`}
                </p>
            </div>
        </section>
    );
};
