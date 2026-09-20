import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { DataTable } from '@ui/table/components/data-table';
import type { ApiPeriod } from '../types/api-period';
import { MONTHS } from '../constants/months';

export const PeriodsPage: FC = () => {
    const canClose = usePermission('contabilidad', 'cerrar_periodo');
    const canReopen = usePermission('contabilidad', 'reabrir_periodo');
    const { sendRequest } = useSendRequest();

    const [refreshToken, setRefreshToken] = useState(0);
    const { data, isLoading } = useResource<{ periodos: ApiPeriod[] }>(
        `/contabilidad/periodos${refreshToken ? `?_r=${refreshToken}` : ''}`,
    );
    const periodos = data?.periodos ?? [];

    const [periodoAReabrir, setPeriodoAReabrir] = useState<ApiPeriod | null>(null);
    const [motivo, setMotivo] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [cerrandoId, setCerrandoId] = useState<number | null>(null);
    const [cerrarError, setCerrarError] = useState<string | null>(null);

    const refrescar = () => setRefreshToken((token) => token + 1);

    const handleCerrar = async (periodo: ApiPeriod) => {
        setCerrarError(null);
        setCerrandoId(periodo.id);

        try {
            await sendRequest(`/contabilidad/periodos/${periodo.id}/cerrar`, { method: 'POST' });
            refrescar();
        } catch (caught) {
            setCerrarError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setCerrandoId(null);
        }
    };

    const handleReabrir = async () => {
        if (!periodoAReabrir) {
            return;
        }
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(`/contabilidad/periodos/${periodoAReabrir.id}/reabrir`, { method: 'POST', body: { motivo } });
            setPeriodoAReabrir(null);
            setMotivo('');
            refrescar();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Períodos contables" />

            {cerrarError && (
                <div className="mb-6">
                    <Alert variant="danger" title="Error" message={cerrarError} onDismiss={() => setCerrarError(null)} />
                </div>
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
                        { key: 'cerrado_en', label: 'Cerrado en', render: (row) => row.cerrado_en ?? '—' },
                        {
                            key: 'acciones',
                            label: '',
                            render: (row) =>
                                row.estado === 'abierto' && canClose ? (
                                    <button
                                        type="button"
                                        onClick={() => void handleCerrar(row)}
                                        disabled={cerrandoId === row.id}
                                        className="text-brand-600 hover:underline disabled:opacity-50 dark:text-brand-400"
                                    >
                                        {cerrandoId === row.id ? 'Cerrando…' : 'Cerrar'}
                                    </button>
                                ) : row.estado === 'cerrado' && canReopen ? (
                                    <button
                                        type="button"
                                        onClick={() => setPeriodoAReabrir(row)}
                                        className="text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        Reabrir
                                    </button>
                                ) : null,
                        },
                    ]}
                    rows={periodos}
                    getRowKey={(row) => row.id}
                    loading={isLoading}
                    emptyMessage="Aún no hay períodos registrados."
                />
            </div>

            <Modal isOpen={periodoAReabrir !== null} onClose={() => setPeriodoAReabrir(null)} size="sm">
                <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Reabrir período</h4>

                {error && (
                    <div className="mb-4 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <Label htmlFor="motivo-reapertura">Motivo de reapertura</Label>
                <textarea
                    id="motivo-reapertura"
                    required
                    rows={3}
                    value={motivo}
                    onChange={(event) => setMotivo(event.target.value)}
                    className="h-auto w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />

                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="outline" onClick={() => setPeriodoAReabrir(null)} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button onClick={() => void handleReabrir()} disabled={isSubmitting || motivo.trim() === ''}>
                        {isSubmitting ? 'Reabriendo…' : 'Reabrir período'}
                    </Button>
                </div>
            </Modal>
        </div>
    );
};
