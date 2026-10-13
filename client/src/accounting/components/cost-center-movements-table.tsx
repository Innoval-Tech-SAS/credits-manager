import type { FC } from 'react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import type { CostCenterMovementsTableProps } from '../props/cost-center-movements-table-props';
import type { ApiCostCenterMovement } from '../types/api-cost-center-movement';

const TIPO_LABELS: Record<ApiCostCenterMovement['tipo'], string> = {
    gasto: 'Gasto',
    asiento: 'Asiento manual',
};

const TIPO_BADGE_CLASSES: Record<ApiCostCenterMovement['tipo'], string> = {
    gasto: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    asiento: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
};

const ESTADO_BADGE_CLASSES: Record<string, string> = {
    anulado: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
};

const ESTADO_BADGE_DEFAULT = 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';

export const CostCenterMovementsTable: FC<CostCenterMovementsTableProps> = ({ centroId }) => {
    const { items, isLoading, page, setPage, totalPages, totalItems } = usePaginatedResource<ApiCostCenterMovement>(
        `/contabilidad/centros-costo/${centroId}/movimientos`,
    );

    return (
        <div>
            <DataTable
                columns={[
                    {
                        key: 'tipo',
                        label: 'Tipo',
                        render: (movimiento) => (
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${TIPO_BADGE_CLASSES[movimiento.tipo]}`}>
                                {TIPO_LABELS[movimiento.tipo]}
                            </span>
                        ),
                    },
                    { key: 'fecha', label: 'Fecha', render: (movimiento) => formatDateEs(movimiento.fecha) },
                    { key: 'documento', label: 'Documento' },
                    { key: 'descripcion', label: 'Descripción', render: (movimiento) => movimiento.descripcion || '—' },
                    { key: 'debito', label: 'Débito', render: (movimiento) => formatMoney(movimiento.debito) },
                    { key: 'credito', label: 'Crédito', render: (movimiento) => formatMoney(movimiento.credito) },
                    {
                        key: 'estado',
                        label: 'Estado',
                        render: (movimiento) => (
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    ESTADO_BADGE_CLASSES[movimiento.estado] ?? ESTADO_BADGE_DEFAULT
                                }`}
                            >
                                {movimiento.estado}
                            </span>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Este centro de costo no tiene movimientos."
            />

            <div className="mt-4">
                <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </div>
            <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                {totalItems} {totalItems === 1 ? 'movimiento' : 'movimientos'} en total.
            </p>
        </div>
    );
};
