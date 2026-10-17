import type { FC } from 'react';
import { useState } from 'react';
import { Lock, Send, Unlock } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { usePermission } from '../../auth/hooks/use-permission';
import { Alert } from '@ui/alert/components/alert';
import { ActionButton } from '@ui/action-button/components/action-button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { DataTable } from '@ui/table/components/data-table';
import { MotivoModal } from '../../shared/components/motivo-modal';
import { ReopenRequestsCard } from '../components/reopen-requests-card';
import type { ApiPeriod } from '../types/api-period';
import type { ApiReopenRequest } from '../types/api-reopen-request';
import { MONTHS } from '../constants/months';

type ReopenMode = 'reabrir' | 'solicitar';

export const PeriodsPage: FC = () => {
    const canClose = usePermission('configuracion_contable', 'cerrar_periodo');
    const canReopen = usePermission('configuracion_contable', 'reabrir_periodo');
    const { sendRequest } = useSendRequest();

    const [refreshToken, setRefreshToken] = useState(0);
    const { data, isLoading } = useResource<{ periodos: ApiPeriod[] }>(
        `/contabilidad/periodos${refreshToken ? `?_r=${refreshToken}` : ''}`,
    );
    const periodos = data?.periodos ?? [];

    const { data: requestsData } = useResource<{ solicitudes: ApiReopenRequest[] }>(
        `/contabilidad/solicitudes-reapertura${refreshToken ? `?_r=${refreshToken}` : ''}`,
    );
    const solicitudes = requestsData?.solicitudes ?? [];
    const pendingPeriodIds = new Set(
        solicitudes.filter((solicitud) => solicitud.estado === 'pendiente').map((solicitud) => solicitud.periodo_id),
    );

    const [reopenTarget, setReopenTarget] = useState<{ periodo: ApiPeriod; mode: ReopenMode } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [cerrandoId, setCerrandoId] = useState<number | null>(null);
    const [cerrarError, setCerrarError] = useState<string | null>(null);
    const [cerrarTarget, setCerrarTarget] = useState<ApiPeriod | null>(null);

    const refrescar = () => setRefreshToken((token) => token + 1);

    const handleCerrar = async () => {
        if (!cerrarTarget) {
            return;
        }

        setCerrarError(null);
        setCerrandoId(cerrarTarget.id);

        try {
            await sendRequest(`/contabilidad/periodos/${cerrarTarget.id}/cerrar`, { method: 'POST' });
            setCerrarTarget(null);
            refrescar();
        } catch (caught) {
            setCerrarError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setCerrandoId(null);
        }
    };

    const handleReabrir = async (motivo: string) => {
        if (!reopenTarget) {
            return;
        }
        setError(null);
        setIsSubmitting(true);

        const action = reopenTarget.mode === 'reabrir' ? 'reabrir' : 'solicitar-reapertura';

        try {
            await sendRequest(`/contabilidad/periodos/${reopenTarget.periodo.id}/${action}`, { method: 'POST', body: { motivo } });
            setReopenTarget(null);
            refrescar();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const isRequestMode = reopenTarget?.mode === 'solicitar';

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Períodos contables" />

            {cerrarError && (
                <div className="mb-6">
                    <Alert variant="danger" title="Error" message={cerrarError} onDismiss={() => setCerrarError(null)} />
                </div>
            )}

            {error && (
                <div className="mb-6">
                    <Alert variant="danger" title="Error" message={error} onDismiss={() => setError(null)} />
                </div>
            )}

            {(canClose || canReopen) && solicitudes.length > 0 && (
                <ReopenRequestsCard solicitudes={solicitudes} canResolve={canReopen} onResolved={refrescar} />
            )}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'anio', label: 'Año' },
                        { key: 'mes', label: 'Mes', render: (row) => MONTHS[row.mes - 1] ?? row.mes },
                        {
                            key: 'estado',
                            label: 'Estado',
                            render: (row) => (
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        row.estado === 'cerrado'
                                            ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                            : 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                    }`}
                                >
                                    {row.estado}
                                </span>
                            ),
                        },
                        { key: 'cerrado_en', label: 'Cerrado en', render: (row) => formatDateTimeEs(row.cerrado_en) || '—' },
                        {
                            key: 'acciones',
                            label: '',
                            render: (row) => {
                                if (row.estado === 'abierto') {
                                    return canClose ? (
                                        <ActionButton
                                            icon={<Lock className="size-5" />}
                                            label={cerrandoId === row.id ? 'Cerrando…' : 'Cerrar'}
                                            disabled={cerrandoId === row.id}
                                            onClick={() => setCerrarTarget(row)}
                                        />
                                    ) : null;
                                }

                                if (canReopen) {
                                    return (
                                        <ActionButton
                                            icon={<Unlock className="size-5" />}
                                            label="Reabrir"
                                            onClick={() => setReopenTarget({ periodo: row, mode: 'reabrir' })}
                                        />
                                    );
                                }

                                if (!canClose) {
                                    return null;
                                }

                                return pendingPeriodIds.has(row.id) ? (
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Solicitud pendiente</span>
                                ) : (
                                    <ActionButton
                                        icon={<Send className="size-5" />}
                                        label="Solicitar reapertura"
                                        onClick={() => setReopenTarget({ periodo: row, mode: 'solicitar' })}
                                    />
                                );
                            },
                        },
                    ]}
                    rows={periodos}
                    getRowKey={(row) => row.id}
                    loading={isLoading}
                    emptyMessage="Aún no hay períodos registrados."
                    maxHeightClassName="max-h-[65vh]"
                />
            </div>

            <ConfirmModal
                isOpen={cerrarTarget !== null}
                onClose={() => setCerrarTarget(null)}
                onConfirm={() => void handleCerrar()}
                variant="warning"
                title="Cerrar período"
                description={
                    cerrarTarget
                        ? `¿Seguro que quieres cerrar ${MONTHS[cerrarTarget.mes - 1] ?? cerrarTarget.mes} de ${cerrarTarget.anio}? Ya no se podrán registrar ni modificar comprobantes en este período hasta que se reabra.`
                        : ''
                }
                confirmLabel="Cerrar período"
                isConfirming={cerrandoId !== null}
            />

            <MotivoModal
                isOpen={reopenTarget !== null}
                title={isRequestMode ? 'Solicitar reapertura' : 'Reabrir período'}
                description={
                    isRequestMode
                        ? 'Un administrador debe autorizar la solicitud antes de que el período se reabra.'
                        : 'El período vuelve a aceptar comprobantes hasta que se cierre de nuevo.'
                }
                confirmLabel={isRequestMode ? 'Enviar solicitud' : 'Reabrir período'}
                isSubmitting={isSubmitting}
                onClose={() => setReopenTarget(null)}
                onConfirm={(motivo) => void handleReabrir(motivo)}
            />
        </div>
    );
};
