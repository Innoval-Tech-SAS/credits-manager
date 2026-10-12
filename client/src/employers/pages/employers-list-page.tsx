import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Briefcase, Search } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildEmployerTableColumns } from '../utils/build-employer-table-columns';
import type { ApiEmployer } from '../types/api-employer';
import type { EmployersListLocationState } from '../types/employers-list-location-state';

export const EmployersListPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const canCreate = usePermission('empleadores', 'crear');
    const canEdit = usePermission('empleadores', 'editar');
    const canDelete = usePermission('empleadores', 'eliminar');

    const [employerToDelete, setEmployerToDelete] = useState<ApiEmployer | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<EmployersListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, sortKey, sortDirection, toggleSort, refetch } =
        usePaginatedResource<ApiEmployer>('/empleadores');

    const handleEdit = (row: ApiEmployer) => {
        navigate(`/empleadores/${row.id}/editar`);
    };

    const confirmDelete = async () => {
        if (!employerToDelete) {
            return;
        }

        setDeleteError(null);
        setIsDeleting(true);

        try {
            await sendRequest(`/empleadores/${employerToDelete.id}`, { method: 'DELETE' });
            setEmployerToDelete(null);
            void refetch();
        } catch (caught) {
            setDeleteError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsDeleting(false);
        }
    };

    const columns = buildEmployerTableColumns({
        canEdit,
        canDelete,
        onEdit: handleEdit,
        onDelete: setEmployerToDelete,
    });

    return (
        <ModuleListPage
            pageTitle="Empleadores"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={deleteError}
            onDismissError={() => setDeleteError(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'empleador registrado' : 'empleadores registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            id="buscar-empleador"
                            name="buscar-empleador"
                            placeholder="Buscar por nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<Briefcase className="size-4" />} onClick={() => navigate('/empleadores/crear')}>
                            Crear empleador
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <ConfirmModal
                    isOpen={employerToDelete !== null}
                    onClose={() => setEmployerToDelete(null)}
                    onConfirm={() => void confirmDelete()}
                    variant="danger"
                    title="Eliminar empleador"
                    description={
                        employerToDelete
                            ? `¿Seguro que quieres eliminar a "${employerToDelete.nombre}"? Esta acción NO se puede revertir.`
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
                emptyMessage="No hay empleadores que coincidan con la búsqueda."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
