import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { DataTable } from '@ui/table/components/data-table';
import { CREDIT_STATUS_BADGE_CLASSES } from '../../credits/constants/credit-status-badge-classes';
import { CREDIT_STATUS_LABEL } from '../../credits/constants/credit-status-label';
import type { ClientCreditsTableProps } from '../props/client-credits-table-props';
import { Eye } from 'lucide-react';

export const ClientCreditsTable: FC<ClientCreditsTableProps> = ({ creditos, onView }) => (
    <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Créditos</h2>
        <DataTable
            columns={[
                { key: 'id', label: '#', render: (row) => `#${row.id}` },
                { key: 'valor_prestamo', label: 'Valor', render: (row) => formatMoney(row.valor_prestamo) },
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
                    key: 'acciones',
                    label: '',
                    render: (row) => (
                        <div className="text-right">
                            <Button iconOnly startIcon={<Eye className="size-4" />} variant="outline" onClick={() => onView(row.id)}>
                                Ver
                            </Button>
                        </div>
                    ),
                },
            ]}
            rows={creditos}
            getRowKey={(row) => row.id}
            emptyMessage="Este cliente aún no tiene créditos."
        />
    </div>
);
