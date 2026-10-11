import type { FC } from 'react';
import { useState } from 'react';
import { RotateCcw } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Alert } from '@ui/alert/components/alert';
import { DataTable } from '@ui/table/components/data-table';
import { DISPATCH_STATUS_LABELS } from '../../email/constants/email-options';
import type { InvoiceDispatchesCardProps } from '../props/invoice-dispatches-card-props';
import type { ApiInvoiceDispatch, ApiInvoiceDispatchHistory } from '../types/api-invoice-dispatch';

/** Correos del documento (envíos y reenvíos), con "Reintentar" sobre los que quedaron fallidos. */
export const InvoiceDispatchesCard: FC<InvoiceDispatchesCardProps> = ({ documentoId }) => {
    const { data, refetch } = useResource<ApiInvoiceDispatchHistory>(`/facturacion/documentos/${documentoId}/envios`);
    const { sendRequest } = useSendRequest();

    const [retryingId, setRetryingId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const handleRetry = async (envioId: number) => {
        setError(null);
        setRetryingId(envioId);

        try {
            await sendRequest(`/facturacion/documentos/${documentoId}/envios/${envioId}/reintentar`, { method: 'POST' });
            refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setRetryingId(null);
        }
    };

    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Correos enviados</h3>

            {error && (
                <div className="mb-4">
                    <Alert variant="danger" title="No se pudo reintentar el envío" message={error} onDismiss={() => setError(null)} />
                </div>
            )}

            <DataTable<ApiInvoiceDispatch>
                columns={[
                    { key: 'fecha', label: 'Fecha', render: (envio) => (envio.creado_en ? new Date(envio.creado_en).toLocaleString('es-CO') : '—') },
                    { key: 'destinatario', label: 'Destinatario' },
                    { key: 'asunto', label: 'Asunto' },
                    {
                        key: 'estado',
                        label: 'Resultado',
                        render: (envio) => (
                            <div className="flex flex-col gap-1">
                                <span className={`w-fit rounded-full px-2.5 py-0.5 text-xs font-medium ${DISPATCH_STATUS_LABELS[envio.estado].className}`}>
                                    {DISPATCH_STATUS_LABELS[envio.estado].label}
                                </span>
                                {envio.detalle_error && <span className="text-xs text-error-600 dark:text-error-500">{envio.detalle_error}</span>}
                            </div>
                        ),
                    },
                    {
                        key: 'acciones',
                        label: 'Acción',
                        render: (envio) =>
                            envio.estado === 'fallido' ? (
                                <button
                                    type="button"
                                    disabled={retryingId !== null}
                                    onClick={() => void handleRetry(envio.id)}
                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 disabled:opacity-50 dark:text-brand-400"
                                >
                                    <RotateCcw className="size-4" />
                                    {retryingId === envio.id ? 'Reintentando...' : 'Reintentar'}
                                </button>
                            ) : (
                                <span className="text-gray-400 dark:text-gray-500">—</span>
                            ),
                    },
                ]}
                rows={data?.envios ?? []}
                getRowKey={(envio) => envio.id}
                loading={!data}
                emptyMessage="Aún no se han enviado correos de este documento."
            />
        </section>
    );
};
