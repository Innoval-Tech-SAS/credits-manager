import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Search, ShieldCheck } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { buildRoleTableColumns } from '../utils/build-role-table-columns';
import type { ApiRoleWithPermisos } from '../types/api-role';
import type { RolesListLocationState } from '../types/roles-list-location-state';

export const RolesListPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const {
        items,
        isLoading,
        error: loadError,
        page,
        setPage,
        totalPages,
        totalItems,
        search,
        setSearch,
        refetch,
    } = usePaginatedResource<ApiRoleWithPermisos>('/roles');

    const [roleToDelete, setRoleToDelete] = useState<ApiRoleWithPermisos | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<RolesListLocationState>();

    const handleEdit = (row: ApiRoleWithPermisos) => {
        navigate(`/roles/${row.id}/editar`);
    };

    const confirmDelete = async () => {
        if (!roleToDelete) {
            return;
        }

        setDeleteError(null);
        setIsDeleting(true);

        try {
            await sendRequest(`/roles/${roleToDelete.id}`, { method: 'DELETE' });
            setRoleToDelete(null);
            refetch();
        } catch (caught) {
            setDeleteError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsDeleting(false);
        }
    };

    const columns = buildRoleTableColumns({ onEdit: handleEdit, onDelete: setRoleToDelete });

    return (
        <ModuleListPage
            pageTitle="Roles"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={deleteError ?? loadError}
            onDismissError={() => setDeleteError(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'rol registrado' : 'roles registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            id="buscar-rol"
                            name="buscar-rol"
                            placeholder="Buscar por nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <Button collapseBelow="md" startIcon={<ShieldCheck className="size-4" />} onClick={() => navigate('/roles/crear')}>
                        Crear rol
                    </Button>
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <ConfirmModal
                    isOpen={roleToDelete !== null}
                    onClose={() => setRoleToDelete(null)}
                    onConfirm={() => void confirmDelete()}
                    variant="danger"
                    title="Eliminar rol"
                    description={
                        roleToDelete
                            ? `¿Seguro que quieres eliminar el rol "${roleToDelete.nombre}"? Esta acción NO se puede revertir.`
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
                loading={isLoading}
                emptyMessage="No hay roles registrados."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
