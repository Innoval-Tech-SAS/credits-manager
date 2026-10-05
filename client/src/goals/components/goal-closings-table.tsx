import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { DataTable } from '@ui/table/components/data-table';
import { GOAL_TYPE_LABELS } from '../constants/goal-status';
import type { GoalClosingsTableProps } from '../props/goal-closings-table-props';
import { GoalStatusBadge } from './goal-status-badge';

export const GoalClosingsTable: FC<GoalClosingsTableProps> = ({ cierres }) => (
    <DataTable
        columns={[
            { key: 'tipo', label: 'Tipo', render: (row) => GOAL_TYPE_LABELS[row.tipo] },
            { key: 'asesor', label: 'Asesor', render: (row) => row.usuario ?? 'Empresa' },
            { key: 'objetivo', label: 'Objetivo', render: (row) => formatMoney(row.objetivo) },
            { key: 'ejecutado', label: 'Ejecutado', render: (row) => formatMoney(row.ejecutado) },
            { key: 'porcentaje', label: '%', render: (row) => `${row.porcentaje}%` },
            { key: 'estado', label: 'Estado', render: (row) => <GoalStatusBadge estado={row.estado} /> },
            { key: 'motivo', label: 'Motivo', render: (row) => row.motivo ?? '—' },
            { key: 'cerrado_en', label: 'Cerrado', render: (row) => new Date(row.cerrado_en).toLocaleDateString('es-CO') },
        ]}
        rows={cierres}
        getRowKey={(row) => row.id}
        emptyMessage="Todavía no hay cierres para este período."
    />
);
