import type { FC } from 'react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import type { ApiImportError } from '../types/api-import-error';
import type { ImportErrorsTableProps } from '../props/import-errors-table-props';

export const ImportErrorsTable: FC<ImportErrorsTableProps> = ({ importId }) => {
    const { items, isLoading, page, setPage, totalPages } = usePaginatedResource<ApiImportError>(`/importaciones/${importId}/errores`);

    return (
        <div className="flex flex-col gap-4">
            <DataTable
                columns={[
                    { key: 'fila', label: 'Fila', render: (row) => row.fila ?? '—' },
                    { key: 'columna', label: 'Columna', render: (row) => row.columna ?? '—' },
                    { key: 'severidad', label: 'Tipo', render: (row) => (row.severidad === 'aviso' ? 'Aviso' : 'Error') },
                    { key: 'fase', label: 'Fase', render: (row) => (row.fase === 'escritura' ? 'Escritura' : 'Validación') },
                    { key: 'motivo', label: 'Motivo' },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin errores ni avisos."
            />
            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
    );
};
