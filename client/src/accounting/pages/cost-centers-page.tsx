import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Eye, Layers, Pencil, Search, Trash2 } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { CreateButton } from '@ui/button/components/create-button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import type { CostCentersListLocationState } from '../types/cost-centers-list-location-state';
import type { ApiCostCenter } from '../types/api-cost-center';

export const CostCentersPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading: isMutating } = useSendRequest();

    const canCreate = usePermission('configuracion_contable', 'crear_centros_costo');
    const canEdit = usePermission('configuracion_contable', 'editar_centros_costo');
    const canDelete = usePermission('configuracion_contable', 'eliminar_centros_costo');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<CostCentersListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, refetch } =
        usePaginatedResource<ApiCostCenter>('/contabilidad/centros-costo');

    const [centroACambiarEstado, setCentroACambiarEstado] = useState<ApiCostCenter | null>(null);
    const [centroAEliminar, setCentroAEliminar] = useState<ApiCostCenter | null>(null);
    const [error, setError] = useState<string | null>(null);

    const confirmarCambioEstado = async () => {
        if (!centroACambiarEstado) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/contabilidad/centros-costo/${centroACambiarEstado.id}`, {
                method: 'PATCH',
                body: { activo: !centroACambiarEstado.activo },
            });
            setCentroACambiarEstado(null);
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const confirmarEliminar = async () => {
        if (!centroAEliminar) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/contabilidad/centros-costo/${centroAEliminar.id}`, { method: 'DELETE' });
            setCentroAEliminar(null);
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <ModuleListPage
            pageTitle="Centros de costo"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={error}
            onDismissError={() => setError(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'centro registrado' : 'centros registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            id="buscar-centro-costo"
                            name="buscar-centro-costo"
                            placeholder="Buscar por código o nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <CreateButton label="centro de costo" icon={<Layers className="size-4" />} onClick={() => navigate('/contabilidad/centros-costo/crear')} />
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <>
                    <ConfirmModal
                        isOpen={centroACambiarEstado !== null}
                        onClose={() => setCentroACambiarEstado(null)}
                        onConfirm={() => void confirmarCambioEstado()}
                        variant={centroACambiarEstado?.activo ? 'danger' : 'success'}
                        title={centroACambiarEstado?.activo ? 'Inhabilitar centro de costo' : 'Activar centro de costo'}
                        description={
                            centroACambiarEstado
                                ? `¿Seguro que quieres ${centroACambiarEstado.activo ? 'inhabilitar' : 'activar'} el centro "${centroACambiarEstado.codigo} - ${centroACambiarEstado.nombre}"?`
                                : ''
                        }
                        confirmLabel={centroACambiarEstado?.activo ? 'Inhabilitar' : 'Activar'}
                        isConfirming={isMutating}
                    />

                    <ConfirmModal
                        isOpen={centroAEliminar !== null}
                        onClose={() => setCentroAEliminar(null)}
                        onConfirm={() => void confirmarEliminar()}
                        variant="danger"
                        title="Eliminar centro de costo"
                        description={`El centro "${centroAEliminar?.codigo} - ${centroAEliminar?.nombre}" se eliminará. Esta acción no se puede deshacer.`}
                        confirmLabel="Eliminar"
                        isConfirming={isMutating}
                    />
                </>
            }
        >
            <DataTable<ApiCostCenter>
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    {
                        key: 'activo',
                        label: 'Estado',
                        render: (centro) => (
                            <div className="flex items-center gap-3">
                                <span>{centro.activo ? 'Activo' : 'Inactivo'}</span>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setCentroACambiarEstado(centro)}
                                        className="text-sm font-medium text-brand-500 hover:text-brand-600"
                                    >
                                        {centro.activo ? 'Inhabilitar' : 'Activar'}
                                    </button>
                                )}
                            </div>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (centro) => (
                            <div className="flex items-center gap-3">
                                <ActionButton
                                    icon={<Eye className="size-5" />}
                                    label="Ver"
                                    onClick={() => navigate(`/contabilidad/centros-costo/${centro.id}`)}
                                />
                                {canEdit && (
                                    <ActionButton
                                        icon={<Pencil className="size-5" />}
                                        label="Editar"
                                        onClick={() => navigate(`/contabilidad/centros-costo/${centro.id}/editar`)}
                                    />
                                )}
                                {canDelete && (
                                    <ActionButton
                                        icon={<Trash2 className="size-5" />}
                                        label="Eliminar"
                                        variant="danger"
                                        onClick={() => setCentroAEliminar(centro)}
                                    />
                                )}
                            </div>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin centros de costo registrados."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
