import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Search, UserPlus } from 'lucide-react';

import { PermissionExportButton } from '../../shared/components/permission-export-button';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Select } from '@ui/forms/components/select';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { CLIENT_CREDIT_STATUS_FILTER_OPTIONS, CLIENT_STATUS_FILTER_OPTIONS } from '../constants/client-status-filter-options';
import { buildClientTableColumns } from '../utils/build-client-table-columns';
import type { ApiClient } from '../types/api-client';
import type { ClientsListLocationState } from '../types/clients-list-location-state';

export const ClientsListPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const canCreate = usePermission('clientes', 'crear');
    const canView = usePermission('clientes', 'ver');
    const canEdit = usePermission('clientes', 'editar');
    const canDelete = usePermission('clientes', 'eliminar');

    const [clientToDelete, setClientToDelete] = useState<ApiClient | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ClientsListLocationState>();

    const [estado, setEstado] = useState('');
    const [estadoCredito, setEstadoCredito] = useState('');

    const {
        items,
        isLoading,
        page,
        setPage,
        totalPages,
        totalItems,
        search,
        setSearch,
        sortKey,
        sortDirection,
        toggleSort,
        refetch,
    } = usePaginatedResource<ApiClient>('/clientes', { estado, estado_credito: estadoCredito });

    const handleEdit = (row: ApiClient) => {
        navigate(`/clientes/${row.id}/editar`);
    };

    const handleView = (row: ApiClient) => {
        navigate(`/clientes/${row.id}`);
    };

    const confirmDelete = async () => {
        if (!clientToDelete) {
            return;
        }

        setDeleteError(null);
        setIsDeleting(true);

        try {
            await sendRequest(`/clientes/${clientToDelete.id}`, { method: 'DELETE' });
            setClientToDelete(null);
            void refetch();
        } catch (caught) {
            setDeleteError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsDeleting(false);
        }
    };

    const columns = buildClientTableColumns({
        canView,
        canEdit,
        canDelete,
        onEdit: handleEdit,
        onView: handleView,
        onDelete: setClientToDelete,
    });

    return (
        <ModuleListPage
            pageTitle="Clientes"
            successMessage={successMessage ?? exportMessage}
            onDismissSuccess={() => {
                setSuccessMessage(null);
                setExportMessage(null);
            }}
            errorMessage={deleteError ?? exportError}
            onDismissError={() => {
                setDeleteError(null);
                setExportError(null);
            }}
            countLabel={`${totalItems} ${totalItems === 1 ? 'cliente registrado' : 'clientes registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Nombre, documento o empresa…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-44">
                        <Select
                            placeholder="Estado"
                            options={CLIENT_STATUS_FILTER_OPTIONS}
                            defaultValue={estado}
                            onChange={(value) => {
                                setEstado(value);
                                setPage(1);
                            }}
                        />
                    </div>

                    <div className="w-full sm:w-48">
                        <Select
                            placeholder="Estado de crédito"
                            options={CLIENT_CREDIT_STATUS_FILTER_OPTIONS}
                            defaultValue={estadoCredito}
                            onChange={(value) => {
                                setEstadoCredito(value);
                                setPage(1);
                            }}
                        />
                    </div>

                    <PermissionExportButton
                        permiso="clientes.exportar_listado"
                        endpoint="/clientes/exportar"
                        filters={{ search, estado, estado_credito: estadoCredito }}
                        onRequested={() => {
                            setExportError(null);
                            setExportMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos cuando esté lista para descargar.');
                        }}
                        onError={(message) => {
                            setExportMessage(null);
                            setExportError(message);
                        }}
                    />

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<UserPlus className="size-4" />} onClick={() => navigate('/clientes/crear')}>
                            Crear cliente
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <ConfirmModal
                    isOpen={clientToDelete !== null}
                    onClose={() => setClientToDelete(null)}
                    onConfirm={() => void confirmDelete()}
                    variant="danger"
                    title="Eliminar cliente"
                    description={
                        clientToDelete
                            ? `¿Seguro que quieres eliminar a "${clientToDelete.tercero.nombre}"? Esta acción NO se puede revertir.`
                            : ''
                    }
                    confirmLabel="Eliminar"
                    isConfirming={isDeleting}
                />
            }
        >
            <DataTable
                columns={columns}
                rows={items}
                getRowKey={(row) => row.id}
                sortKey={sortKey}
                sortDirection={sortDirection}
                onSortChange={toggleSort}
                loading={isLoading}
                emptyMessage="No hay clientes que coincidan con la búsqueda."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
