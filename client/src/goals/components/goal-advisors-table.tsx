import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { DataTable } from '@ui/table/components/data-table';
import type { GoalAdvisorsTableProps } from '../props/goal-advisors-table-props';
import { GoalStatusBadge } from './goal-status-badge';

export const GoalAdvisorsTable: FC<GoalAdvisorsTableProps> = ({ asesores }) => (
    <DataTable
        columns={[
            { key: 'nombre', label: 'Asesor' },
            { key: 'objetivo', label: 'Objetivo', render: (row) => formatMoney(row.cumplimiento.objetivo) },
            { key: 'ejecutado', label: 'Ejecutado', render: (row) => formatMoney(row.cumplimiento.ejecutado) },
            { key: 'porcentaje', label: '%', render: (row) => `${row.cumplimiento.porcentaje}%` },
            { key: 'brecha', label: 'Brecha', render: (row) => formatMoney(row.cumplimiento.brecha) },
            { key: 'estado', label: 'Estado', render: (row) => <GoalStatusBadge estado={row.cumplimiento.estado} /> },
        ]}
        rows={asesores}
        getRowKey={(row) => row.usuario_id}
        emptyMessage="Esta meta no está distribuida entre asesores."
    />
);
