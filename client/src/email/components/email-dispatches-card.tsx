import type { FC } from 'react';
import { RotateCcw } from 'lucide-react';

import { DataTable } from '@ui/table/components/data-table';
import { DISPATCH_STATUS_LABELS } from '../constants/email-options';
import type { EmailDispatchesCardProps } from '../props/email-dispatches-card-props';
import type { ApiEmailDispatch } from '../types/api-email-dispatch';

/** Últimos correos de cobranza enviados al cliente, con su resultado. */
export const EmailDispatchesCard: FC<EmailDispatchesCardProps> = ({ envios, onRetry, retryingId = null }) => (
    <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
        <h2 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Correos de cobranza</h2>
        <DataTable<ApiEmailDispatch>
            columns={[
                { key: 'fecha', label: 'Fecha', render: (envio) => (envio.creado_en ? new Date(envio.creado_en).toLocaleString('es-CO') : '—') },
                { key: 'destinatario', label: 'Destinatario', render: (envio) => envio.destinatario },
                { key: 'asunto', label: 'Asunto', render: (envio) => envio.asunto },
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
                ...(onRetry
                    ? [
                          {
                              key: 'acciones',
                              label: 'Acción',
                              render: (envio: ApiEmailDispatch) =>
                                  envio.estado === 'fallido' ? (
                                      <button
                                          type="button"
                                          disabled={retryingId !== null}
                                          onClick={() => onRetry(envio.id)}
                                          className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 disabled:opacity-50 dark:text-brand-400"
                                      >
                                          <RotateCcw className="size-4" />
                                          {retryingId === envio.id ? 'Reintentando...' : 'Reintentar'}
                                      </button>
                                  ) : (
                                      <span className="text-gray-400 dark:text-gray-500">—</span>
                                  ),
                          },
                      ]
                    : []),
            ]}
            rows={envios}
            getRowKey={(envio) => envio.id}
            emptyMessage="Aún no se han enviado correos de cobranza a este cliente."
        />
    </section>
);
