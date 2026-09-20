import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import type { PortfolioCurrentTableProps } from '../props/portfolio-current-table-props';

export const PortfolioCurrentTable: FC<PortfolioCurrentTableProps> = ({ entradas, onView }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Al día</h2>

        <DataTable
            columns={[
                { key: 'cliente', label: 'Cliente', render: (row) => row.credito.cliente?.tercero.nombre ?? '—' },
                { key: 'telefono', label: 'Teléfono', render: (row) => row.credito.cliente?.tercero.celular ?? '—' },
                { key: 'saldo', label: 'Saldo', render: (row) => formatMoney(row.saldo) },
                {
                    key: 'acciones',
                    label: '',
                    render: (row) => (
                        <div className="text-right">
                            <Button variant="outline" onClick={() => onView(row.credito.id)}>
                                Ver
                            </Button>
                        </div>
                    ),
                },
            ]}
            rows={entradas}
            getRowKey={(row) => row.credito.id}
            emptyMessage="No hay créditos al día actualmente."
            maxHeightClassName="max-h-[65vh]"
        />
    </div>
);
