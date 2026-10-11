import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Eye, FileText, Pencil, Search, Trash2 } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import type { VoucherTypesListLocationState } from '../types/voucher-types-list-location-state';
import type { ApiVoucherType } from '../types/api-voucher-type';

export const VoucherTypesPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading: isMutating } = useSendRequest();

    const canCreate = usePermission('configuracion_contable', 'crear');
    const canEdit = usePermission('configuracion_contable', 'editar');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<VoucherTypesListLocationState>();

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, refetch } =
        usePaginatedResource<ApiVoucherType>('/contabilidad/tipos-comprobante');

    const [tipoACambiarEstado, setTipoACambiarEstado] = useState<ApiVoucherType | null>(null);
    const [tipoAEliminar, setTipoAEliminar] = useState<ApiVoucherType | null>(null);
    const [error, setError] = useState<string | null>(null);

    const confirmarCambioEstado = async () => {
        if (!tipoACambiarEstado) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/contabilidad/tipos-comprobante/${tipoACambiarEstado.id}`, {
                method: 'PATCH',
                body: { activo: !tipoACambiarEstado.activo },
            });
            setTipoACambiarEstado(null);
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const confirmarEliminar = async () => {
        if (!tipoAEliminar) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/contabilidad/tipos-comprobante/${tipoAEliminar.id}`, { method: 'DELETE' });
            setTipoAEliminar(null);
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <ModuleListPage
            pageTitle="Tipos de comprobante"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={error}
            onDismissError={() => setError(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'tipo registrado' : 'tipos registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por código o nombre…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/contabilidad/tipos-comprobante/crear')}>
                            Crear tipo de comprobante
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <>
                    <ConfirmModal
                        isOpen={tipoACambiarEstado !== null}
                        onClose={() => setTipoACambiarEstado(null)}
                        onConfirm={() => void confirmarCambioEstado()}
                        variant={tipoACambiarEstado?.activo ? 'danger' : 'success'}
                        title={tipoACambiarEstado?.activo ? 'Inhabilitar tipo de comprobante' : 'Activar tipo de comprobante'}
                        description={
                            tipoACambiarEstado
                                ? `¿Seguro que quieres ${tipoACambiarEstado.activo ? 'inhabilitar' : 'activar'} el tipo "${tipoACambiarEstado.codigo} - ${tipoACambiarEstado.nombre}"?`
                                : ''
                        }
                        confirmLabel={tipoACambiarEstado?.activo ? 'Inhabilitar' : 'Activar'}
                        isConfirming={isMutating}
                    />

                    <ConfirmModal
                        isOpen={tipoAEliminar !== null}
                        onClose={() => setTipoAEliminar(null)}
                        onConfirm={() => void confirmarEliminar()}
                        variant="danger"
                        title="Eliminar tipo de comprobante"
                        description={`El tipo "${tipoAEliminar?.codigo} - ${tipoAEliminar?.nombre}" se eliminará. Esta acción no se puede deshacer.`}
                        confirmLabel="Eliminar"
                        isConfirming={isMutating}
                    />
                </>
            }
        >
            <DataTable<ApiVoucherType>
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'consecutivo_actual', label: 'Consecutivo actual' },
                    {
                        key: 'activo',
                        label: 'Estado',
                        render: (tipo) => (
                            <div className="flex items-center gap-3">
                                <span>{tipo.activo ? 'Activo' : 'Inactivo'}</span>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setTipoACambiarEstado(tipo)}
                                        className="text-sm font-medium text-brand-500 hover:text-brand-600"
                                    >
                                        {tipo.activo ? 'Inhabilitar' : 'Activar'}
                                    </button>
                                )}
                            </div>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acciones',
                        render: (tipo) => (
                            <div className="flex items-center gap-3">
                                <ActionButton
                                    icon={<Eye className="size-5" />}
                                    label="Ver"
                                    onClick={() => navigate(`/contabilidad/tipos-comprobante/${tipo.id}`)}
                                />
                                {canEdit && (
                                    <>
                                        <ActionButton
                                            icon={<Pencil className="size-5" />}
                                            label="Editar"
                                            onClick={() => navigate(`/contabilidad/tipos-comprobante/${tipo.id}/editar`)}
                                        />
                                        <ActionButton
                                            icon={<Trash2 className="size-5" />}
                                            label="Eliminar"
                                            variant="danger"
                                            onClick={() => setTipoAEliminar(tipo)}
                                        />
                                    </>
                                )}
                            </div>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin tipos de comprobante registrados."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
