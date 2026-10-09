import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { ListTree, Search } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { ChartOfAccountsListLocationState } from '../types/chart-of-accounts-list-location-state';
import type { ApiAccount } from '../types/api-account';

export const ChartOfAccountsPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('configuracion_contable', 'crear');
    const canEdit = usePermission('configuracion_contable', 'editar');
    const { sendRequest } = useSendRequest();

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ChartOfAccountsListLocationState>();

    const [search, setSearch] = useState('');

    // `useResource` solo vuelve a pedir datos cuando cambia el string del endpoint —
    // este contador fuerza el refetch después de cambiar el estado de una cuenta.
    const [refreshToken, setRefreshToken] = useState(0);
    const { data, isLoading } = useResource<{ cuentas: ApiAccount[] }>(
        `/contabilidad/plan-cuentas?search=${encodeURIComponent(search)}${refreshToken ? `&_r=${refreshToken}` : ''}`,
    );
    const cuentas = data?.cuentas ?? [];

    const [cuentaACambiarEstado, setCuentaACambiarEstado] = useState<ApiAccount | null>(null);
    const [isUpdatingEstado, setIsUpdatingEstado] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const confirmarCambioEstado = async () => {
        if (!cuentaACambiarEstado) {
            return;
        }

        setError(null);
        setIsUpdatingEstado(true);

        try {
            await sendRequest(`/contabilidad/plan-cuentas/${cuentaACambiarEstado.id}`, {
                method: 'PATCH',
                body: { activa: !cuentaACambiarEstado.activa },
            });
            setCuentaACambiarEstado(null);
            setRefreshToken((token) => token + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsUpdatingEstado(false);
        }
    };

    return (
        <ModuleListPage
            pageTitle="Plan de cuentas"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={error}
            onDismissError={() => setError(null)}
            countLabel={`${cuentas.length} ${cuentas.length === 1 ? 'cuenta registrada' : 'cuentas registradas'}`}
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
                        <Button collapseBelow="md" startIcon={<ListTree className="size-4" />} onClick={() => navigate('/contabilidad/plan-cuentas/crear')}>
                            Crear cuenta
                        </Button>
                    )}
                </>
            }
            modals={
                <ConfirmModal
                    isOpen={cuentaACambiarEstado !== null}
                    onClose={() => setCuentaACambiarEstado(null)}
                    onConfirm={() => void confirmarCambioEstado()}
                    variant={cuentaACambiarEstado?.activa ? 'danger' : 'success'}
                    title={cuentaACambiarEstado?.activa ? 'Inhabilitar cuenta' : 'Activar cuenta'}
                    description={
                        cuentaACambiarEstado
                            ? `¿Seguro que quieres ${cuentaACambiarEstado.activa ? 'inhabilitar' : 'activar'} la cuenta "${cuentaACambiarEstado.codigo} - ${cuentaACambiarEstado.nombre}"?`
                            : ''
                    }
                    confirmLabel={cuentaACambiarEstado?.activa ? 'Inhabilitar' : 'Activar'}
                    isConfirming={isUpdatingEstado}
                />
            }
        >
            <DataTable
                columns={[
                    { key: 'codigo', label: 'Código' },
                    {
                        key: 'nombre',
                        label: 'Nombre',
                        render: (cuenta) => (
                            <span style={{ paddingLeft: `${(cuenta.nivel - 1) * 1.25}rem` }}>{cuenta.nombre}</span>
                        ),
                    },
                    { key: 'naturaleza', label: 'Naturaleza' },
                    { key: 'nivel', label: 'Nivel' },
                    { key: 'tipo', label: 'Tipo' },
                    { key: 'permite_movimiento', label: 'Movimiento', render: (cuenta) => (cuenta.permite_movimiento ? 'Sí' : 'No') },
                    {
                        key: 'exige',
                        label: 'Exige',
                        render: (cuenta) => {
                            const requisitos = [
                                cuenta.exige_tercero && 'Tercero',
                                cuenta.exige_documento_cruce && 'Doc. cruce',
                                cuenta.exige_vencimiento && 'Vencimiento',
                                cuenta.exige_centro_costo && 'C. costo',
                            ].filter(Boolean);

                            return requisitos.length > 0 ? requisitos.join(' · ') : '—';
                        },
                    },
                    {
                        key: 'activa',
                        label: 'Estado',
                        render: (cuenta) => (
                            <div className="flex items-center gap-3">
                                <span>{cuenta.activa ? 'Activa' : 'Inactiva'}</span>
                                {canEdit && (
                                    <button
                                        type="button"
                                        onClick={() => setCuentaACambiarEstado(cuenta)}
                                        className="text-sm font-medium text-brand-500 hover:text-brand-600"
                                    >
                                        {cuenta.activa ? 'Inhabilitar' : 'Activar'}
                                    </button>
                                )}
                            </div>
                        ),
                    },
                ]}
                rows={cuentas}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin cuentas registradas."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
