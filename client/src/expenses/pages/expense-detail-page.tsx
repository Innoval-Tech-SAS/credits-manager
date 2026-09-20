import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { Spinner } from '@ui/spinner/components/spinner';
import { DataTable } from '@ui/table/components/data-table';
import type { ApiExpense } from '../types/api-expense';

const RETENTION_LABELS: Record<string, string> = {
    retefuente: 'ReteFuente',
    reteica: 'ReteICA',
    reteiva: 'ReteIVA',
};

export const ExpenseDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const canAnular = usePermission('gastos', 'anular');

    const { data, error, isLoading } = useResource<{ gasto: ApiExpense }>(`/gastos/${id}`);
    const gasto = data?.gasto ?? null;

    const [isConfirmingAnular, setIsConfirmingAnular] = useState(false);
    const [isAnulando, setIsAnulando] = useState(false);
    const [anularError, setAnularError] = useState<string | null>(null);

    const confirmarAnular = async () => {
        if (!gasto) {
            return;
        }

        setAnularError(null);
        setIsAnulando(true);

        try {
            await sendRequest(`/gastos/${gasto.id}`, { method: 'DELETE' });
            navigate('/gastos', { replace: true, state: { message: 'Gasto anulado correctamente.' } });
        } catch (caught) {
            setAnularError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
            setIsConfirmingAnular(false);
        } finally {
            setIsAnulando(false);
        }
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={gasto ? `Gasto — ${gasto.numero_documento}` : 'Gasto'} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {(error || anularError) && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error ?? anularError}
                    </div>
                )}

                {isLoading && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {gasto && (
                    <>
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <p>Fecha: {formatDateEs(gasto.fecha)}</p>
                                <p>Tipo de gasto: {gasto.tipo_gasto?.nombre ?? '—'}</p>
                                {gasto.tercero && <p>Tercero: {gasto.tercero.nombre}</p>}
                                <p>Concepto: {gasto.concepto}</p>
                            </div>

                            <div className="flex items-center gap-3">
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        gasto.estado === 'causado'
                                            ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                            : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                    }`}
                                >
                                    {gasto.estado === 'causado' ? 'Causado' : 'Anulado'}
                                </span>

                                {canAnular && gasto.estado === 'causado' && (
                                    <Button variant="outline" size="sm" onClick={() => setIsConfirmingAnular(true)}>
                                        Anular
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="mb-8 grid grid-cols-2 gap-4 rounded-xl border border-gray-200 p-4 sm:grid-cols-3 lg:grid-cols-6 dark:border-gray-800">
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Valor bruto</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(gasto.valor_bruto)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">IVA</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(gasto.valor_iva)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">ReteFuente</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">−{formatMoney(gasto.valor_retefuente)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">ReteICA</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">−{formatMoney(gasto.valor_reteica)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">ReteIVA</p>
                                <p className="font-semibold text-gray-800 dark:text-white/90">−{formatMoney(gasto.valor_reteiva)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 dark:text-gray-500">Neto a pagar</p>
                                <p className="font-semibold text-brand-600 dark:text-brand-400">{formatMoney(gasto.valor_neto)}</p>
                            </div>
                        </div>

                        <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Trazabilidad de reglas fiscales
                        </p>
                        <DataTable
                            columns={[
                                {
                                    key: 'tipo_retencion',
                                    label: 'Retención',
                                    render: (row) => <span className="font-medium">{RETENTION_LABELS[row.tipo_retencion]}</span>,
                                },
                                {
                                    key: 'incluida',
                                    label: 'Incluida',
                                    render: (row) => (
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                row.incluida
                                                    ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'
                                            }`}
                                        >
                                            {row.incluida ? 'Incluida' : 'Excluida'}
                                        </span>
                                    ),
                                },
                                { key: 'regla', label: 'Regla' },
                                { key: 'tarifa_aplicada', label: 'Tarifa', render: (row) => (row.tarifa_aplicada ? `${row.tarifa_aplicada}%` : '—') },
                                { key: 'detalle', label: 'Detalle', render: (row) => row.detalle ?? '—' },
                            ]}
                            rows={gasto.reglas_fiscales_aplicadas ?? []}
                            getRowKey={(row) => row.id}
                        />
                    </>
                )}
            </div>

            <ConfirmModal
                isOpen={isConfirmingAnular}
                onClose={() => setIsConfirmingAnular(false)}
                onConfirm={() => void confirmarAnular()}
                variant="danger"
                title="Anular gasto"
                description={gasto ? `¿Seguro que quieres anular el gasto #${gasto.id}? Se reversará su comprobante contable.` : ''}
                confirmLabel="Anular gasto"
                isConfirming={isAnulando}
            />
        </div>
    );
};
