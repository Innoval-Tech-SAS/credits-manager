import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Eye, FileInput, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { ImportStatusBadge } from '../components/import-status-badge';
import { IMPORT_TYPE_LABELS } from '../constants/import-types';
import type { ApiImport } from '../types/api-import';
import { formatImportDate } from '../utils/format-import-date';

export const ImportsPage: FC = () => {
    const navigate = useNavigate();
    const canExecute = usePermission('importaciones', 'ejecutar');

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch } = usePaginatedResource<ApiImport>('/importaciones');

    return (
        <ModuleListPage
            pageTitle="Importaciones"
            countLabel={`${totalItems} ${totalItems === 1 ? 'importación registrada' : 'importaciones registradas'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por archivo…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canExecute && (
                        <Button collapseBelow="md" startIcon={<FileInput className="size-4" />} onClick={() => navigate('/importaciones/nueva')}>
                            Nueva importación
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={[
                    { key: 'creado_en', label: 'Fecha', render: (row) => formatImportDate(row.creado_en) },
                    { key: 'tipo', label: 'Hoja', render: (row) => IMPORT_TYPE_LABELS[row.tipo] ?? row.tipo },
                    { key: 'archivo_original', label: 'Archivo' },
                    { key: 'estado', label: 'Estado', render: (row) => <ImportStatusBadge status={row.estado} /> },
                    { key: 'filas_aceptadas', label: 'Aceptadas', render: (row) => row.filas_aceptadas },
                    { key: 'filas_rechazadas', label: 'Rechazadas', render: (row) => row.filas_rechazadas },
                    {
                        key: 'ver',
                        label: '',
                        render: (row) => <ActionButton icon={<Eye className="size-5" />} label="Ver" onClick={() => navigate(`/importaciones/${row.id}`)} />,
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin importaciones registradas."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
