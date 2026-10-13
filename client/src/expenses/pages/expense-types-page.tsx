import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Eye, FileText, Pencil, Trash2 } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { CreateButton } from '@ui/button/components/create-button';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ExpenseTypesListLocationState } from '../types/expense-types-list-location-state';
import type { ApiExpenseType } from '../types/api-expense-type';

export const ExpenseTypesPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading: isMutating } = useSendRequest();

    const canCreate = usePermission('gastos', 'crear_tipos_gasto');
    const canEdit = usePermission('gastos', 'editar_tipos_gasto');
    const canDelete = usePermission('gastos', 'eliminar_tipos_gasto');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ExpenseTypesListLocationState>();
    const { data, isLoading, refetch } = useResource<{ tipos_gasto: ApiExpenseType[] }>('/gastos/tipos-gasto');
    const tiposGasto = data?.tipos_gasto ?? [];

    const [tipoACambiarEstado, setTipoACambiarEstado] = useState<ApiExpenseType | null>(null);
    const [tipoAEliminar, setTipoAEliminar] = useState<ApiExpenseType | null>(null);
    const [error, setError] = useState<string | null>(null);

    const confirmarCambioEstado = async () => {
        if (!tipoACambiarEstado) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/gastos/tipos-gasto/${tipoACambiarEstado.id}`, {
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
            await sendRequest(`/gastos/tipos-gasto/${tipoAEliminar.id}`, { method: 'DELETE' });
            setTipoAEliminar(null);
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <ModuleListPage
            pageTitle="Tipos de gasto"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={error}
            onDismissError={() => setError(null)}
            countLabel={`${tiposGasto.length} ${tiposGasto.length === 1 ? 'tipo registrado' : 'tipos registrados'}`}
            toolbar={
                canCreate && <CreateButton label="tipo de gasto" icon={<FileText className="size-4" />} onClick={() => navigate('/gastos/tipos-gasto/crear')} />
            }
            modals={
                <>
                    <ConfirmModal
                        isOpen={tipoACambiarEstado !== null}
                        onClose={() => setTipoACambiarEstado(null)}
                        onConfirm={() => void confirmarCambioEstado()}
                        variant={tipoACambiarEstado?.activo ? 'danger' : 'success'}
                        title={tipoACambiarEstado?.activo ? 'Inhabilitar tipo de gasto' : 'Activar tipo de gasto'}
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
                        title="Eliminar tipo de gasto"
                        description={`El tipo "${tipoAEliminar?.codigo} - ${tipoAEliminar?.nombre}" se eliminará. Esta acción no se puede deshacer.`}
                        confirmLabel="Eliminar"
                        isConfirming={isMutating}
                    />
                </>
            }
        >
            <DataTable<ApiExpenseType>
                columns={[
                    { key: 'codigo', label: 'Código' },
                    { key: 'nombre', label: 'Nombre' },
                    { key: 'requiere_tercero', label: 'Requiere tercero', render: (tipo) => (tipo.requiere_tercero ? 'Sí' : 'No') },
                    { key: 'aplica_iva', label: 'Aplica IVA', render: (tipo) => (tipo.aplica_iva ? 'Sí' : 'No') },
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
                                    onClick={() => navigate(`/gastos/tipos-gasto/${tipo.id}`)}
                                />
                                {canEdit && (
                                    <ActionButton
                                        icon={<Pencil className="size-5" />}
                                        label="Editar"
                                        onClick={() => navigate(`/gastos/tipos-gasto/${tipo.id}/editar`)}
                                    />
                                )}
                                {canDelete && (
                                    <ActionButton
                                        icon={<Trash2 className="size-5" />}
                                        label="Eliminar"
                                        variant="danger"
                                        onClick={() => setTipoAEliminar(tipo)}
                                    />
                                )}
                            </div>
                        ),
                    },
                ]}
                rows={tiposGasto}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin tipos de gasto registrados."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
