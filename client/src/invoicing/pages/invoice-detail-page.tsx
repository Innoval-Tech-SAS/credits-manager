import type { FC } from 'react';
import { useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router';
import { Ban, Download, FileText, Mail, Send, Trash2, Wallet } from 'lucide-react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useDownloadFile } from '../../shared/hooks/use-download-file';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { Spinner } from '@ui/spinner/components/spinner';
import { DataTable } from '@ui/table/components/data-table';
import { InvoiceDispatchesCard } from '../components/invoice-dispatches-card';
import { InvoiceLinesForm } from '../components/invoice-lines-form';
import { MotivoModal } from '../../shared/components/motivo-modal';
import { InvoiceNoteForm } from '../components/invoice-note-form';
import { InvoicePaymentModal } from '../components/invoice-payment-modal';
import { InvoicePaymentsTable } from '../components/invoice-payments-table';
import { InvoiceSituationBadge } from '../components/invoice-situation-badge';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { ApiInvoiceDetail, ApiInvoicePayment } from '../types/api-invoice';

const ETIQUETA_TIPO = { factura: 'Factura', nota_credito: 'Nota crédito', nota_debito: 'Nota débito' } as const;

/** Acción de corrección pendiente de motivo: anular la factura o reversar un cobro. */
type AccionConMotivo = { tipo: 'anular' } | { tipo: 'reversar'; cobro: ApiInvoicePayment };

export const InvoiceDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const location = useLocation();
    const { sendRequest, isLoading: enviando } = useSendRequest();
    const { download, isDownloading } = useDownloadFile();

    const puedeCrear = usePermission('facturacion', 'crear');
    const puedeAnular = usePermission('facturacion', 'anular');
    const puedeImprimir = usePermission('facturacion', 'imprimir');

    const { data, error, refetch } = useResource<ApiInvoiceDetail>(`/facturacion/documentos/${id}`);

    const [mensaje, setMensaje] = useState<string | null>((location.state as { message?: string } | null)?.message ?? null);
    const [errorAccion, setErrorAccion] = useState<string | null>(null);
    const [confirmarEliminar, setConfirmarEliminar] = useState(false);
    const [lineasEdicion, setLineasEdicion] = useState<InvoiceLineDraft[] | null>(null);
    const [cobroAbierto, setCobroAbierto] = useState(false);
    const [notaAbierta, setNotaAbierta] = useState(false);
    const [accionConMotivo, setAccionConMotivo] = useState<AccionConMotivo | null>(null);
    const [dispatchesVersion, setDispatchesVersion] = useState(0);

    const reportarError = (caught: unknown) => {
        setMensaje(null);
        setErrorAccion(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
    };

    const informarExito = (texto: string) => {
        setErrorAccion(null);
        setMensaje(texto);
        void refetch();
    };

    if (error) {
        return <Alert variant="danger" title="No se pudo cargar el documento" message={error} />;
    }

    if (!data) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const { documento, totales, cobros } = data;
    const esBorrador = documento.estado === 'borrador';
    const esFactura = documento.tipo === 'factura';
    const situacion = totales.situacion;
    const tituloDocumento = documento.numero ?? `${ETIQUETA_TIPO[documento.tipo]} en borrador`;
    const puedeCobrar = esFactura && !esBorrador && puedeCrear && (situacion === 'emitida' || situacion === 'parcialmente_pagada');
    const puedeAnularFactura = esFactura && !esBorrador && puedeAnular && situacion !== 'anulada';

    const emitir = async () => {
        try {
            await sendRequest(`/facturacion/documentos/${documento.id}/emitir`, { method: 'POST' });
            informarExito('Factura emitida. Su número ya no cambia.');
        } catch (caught) {
            reportarError(caught);
        }
    };

    const eliminar = async () => {
        try {
            await sendRequest(`/facturacion/documentos/${documento.id}`, { method: 'DELETE' });
            navigate('/facturacion', { replace: true, state: { message: 'Borrador eliminado.' } });
        } catch (caught) {
            reportarError(caught);
        } finally {
            setConfirmarEliminar(false);
        }
    };

    const guardarEdicion = async () => {
        if (lineasEdicion === null) {
            return;
        }

        try {
            await sendRequest(`/facturacion/documentos/${documento.id}`, {
                method: 'PATCH',
                body: { lineas: lineasEdicion.map((linea) => ({ concepto: linea.concepto, descripcion: linea.descripcion, valor: linea.valor })) },
            });
            setLineasEdicion(null);
            informarExito('Borrador actualizado.');
        } catch (caught) {
            reportarError(caught);
        }
    };

    const reenviarPorCorreo = async () => {
        try {
            await sendRequest(`/facturacion/documentos/${documento.id}/reenviar`, { method: 'POST' });
            setDispatchesVersion((version) => version + 1);
            informarExito('Correo encolado. Se enviará en los próximos minutos.');
        } catch (caught) {
            reportarError(caught);
        }
    };

    const confirmarAccionConMotivo = async (motivo: string) => {
        if (accionConMotivo === null) {
            return;
        }

        const ruta =
            accionConMotivo.tipo === 'anular'
                ? `/facturacion/documentos/${documento.id}/anular`
                : `/facturacion/cobros/${accionConMotivo.cobro.id}/reversar`;

        try {
            await sendRequest(ruta, { method: 'POST', body: { motivo } });
            setAccionConMotivo(null);
            informarExito(
                accionConMotivo.tipo === 'anular'
                    ? 'Factura anulada con nota crédito. Conserva su número y la nota queda asentada y enlazada a la factura.'
                    : 'Cobro reversado. Su asiento quedó anulado con un reverso enlazado.',
            );
        } catch (caught) {
            setAccionConMotivo(null);
            reportarError(caught);
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle={tituloDocumento} />

            {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}
            {errorAccion && <Alert variant="danger" title="No se pudo completar la acción" message={errorAccion} onDismiss={() => setErrorAccion(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{tituloDocumento}</h2>
                            {esFactura && situacion && <InvoiceSituationBadge situacion={situacion} />}
                        </div>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {ETIQUETA_TIPO[documento.tipo]} · {formatDateEs(documento.fecha)} · Cliente {documento.cliente?.tercero.nombre ?? '—'} · Crédito #{documento.credito_id}
                        </p>
                        {documento.origen && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Sobre {documento.origen.numero ?? 'documento de origen'}</p>
                        )}
                        {documento.motivo && <p className="text-sm text-gray-500 dark:text-gray-400">Motivo: {documento.motivo}</p>}
                    </div>

                    <div className="flex flex-wrap gap-3">
                        {esBorrador && puedeCrear && (
                            <>
                                <Button variant="outline" startIcon={<Trash2 className="size-4" />} onClick={() => setConfirmarEliminar(true)}>
                                    Eliminar borrador
                                </Button>
                                <Button startIcon={<Send className="size-4" />} disabled={enviando} onClick={() => void emitir()}>
                                    Emitir factura
                                </Button>
                            </>
                        )}
                        {puedeCobrar && (
                            <Button startIcon={<Wallet className="size-4" />} onClick={() => setCobroAbierto(true)}>
                                Registrar cobro
                            </Button>
                        )}
                        {puedeAnularFactura && (
                            <Button
                                variant="outline"
                                iconOnly
                                startIcon={<Ban className="size-4" />}
                                disabled={enviando}
                                onClick={() => setAccionConMotivo({ tipo: 'anular' })}
                            >
                                Anular factura
                            </Button>
                        )}
                        {!esBorrador && puedeImprimir && (
                            <Button
                                variant="outline"
                                iconOnly
                                startIcon={<Download className="size-4" />}
                                disabled={isDownloading}
                                onClick={() => void download(`/facturacion/documentos/${documento.id}/pdf`, `${documento.numero ?? 'documento'}.pdf`)}
                            >
                                {isDownloading ? 'Generando…' : 'Descargar PDF'}
                            </Button>
                        )}
                        {!esBorrador && puedeImprimir && (
                            <Button variant="outline" iconOnly startIcon={<Mail className="size-4" />} disabled={enviando} onClick={() => void reenviarPorCorreo()}>
                                Reenviar por correo
                            </Button>
                        )}
                        {!esBorrador && puedeAnular && (
                            <Button variant="outline" iconOnly startIcon={<FileText className="size-4" />} onClick={() => setNotaAbierta(true)}>
                                Emitir nota
                            </Button>
                        )}
                    </div>
                </div>

                {situacion === 'anulada' && (
                    <p className="mt-4 text-sm font-medium text-error-600 dark:text-error-500">
                        Factura anulada: sus notas crédito cubren el total. El número se conserva y la nota queda asentada y enlazada a la factura.
                    </p>
                )}
            </div>

            {!esBorrador && puedeImprimir && <InvoiceDispatchesCard key={dispatchesVersion} documentoId={documento.id} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Líneas</h3>
                    {esBorrador && puedeCrear && lineasEdicion === null && (
                        <Button variant="outline" size="sm" onClick={() => setLineasEdicion((documento.lineas ?? []).map(toDraft))}>
                            Editar líneas
                        </Button>
                    )}
                </div>

                {lineasEdicion !== null ? (
                    <div className="flex flex-col gap-4">
                        <InvoiceLinesForm lineas={lineasEdicion} onChange={setLineasEdicion} />
                        <div className="flex justify-end gap-3">
                            <Button variant="outline" onClick={() => setLineasEdicion(null)}>
                                Cancelar
                            </Button>
                            <Button disabled={enviando} onClick={() => void guardarEdicion()}>
                                Guardar líneas
                            </Button>
                        </div>
                    </div>
                ) : (
                    <DataTable
                        columns={[
                            { key: 'concepto', label: 'Concepto' },
                            { key: 'descripcion', label: 'Descripción' },
                            { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.valor) },
                        ]}
                        rows={documento.lineas ?? []}
                        getRowKey={(row) => row.id}
                        emptyMessage="Sin líneas."
                        maxHeightClassName="max-h-80"
                    />
                )}

                <dl className="mt-4 grid grid-cols-2 gap-2 text-sm md:ml-auto md:max-w-md">
                    <dt className="text-gray-500 dark:text-gray-400">Total</dt>
                    <dd className="text-right font-semibold text-gray-800 dark:text-white/90">{formatMoney(totales.total)}</dd>
                    {esFactura && (
                        <>
                            <dt className="text-gray-500 dark:text-gray-400">Notas crédito</dt>
                            <dd className="text-right text-gray-800 dark:text-white/90">{formatMoney(totales.total_nota_credito ?? '0')}</dd>
                            <dt className="text-gray-500 dark:text-gray-400">Notas débito</dt>
                            <dd className="text-right text-gray-800 dark:text-white/90">{formatMoney(totales.total_nota_debito ?? '0')}</dd>
                            <dt className="text-gray-500 dark:text-gray-400">Cobrado</dt>
                            <dd className="text-right text-gray-800 dark:text-white/90">{formatMoney(totales.total_cobrado ?? '0')}</dd>
                            <dt className="text-gray-500 dark:text-gray-400">Saldo</dt>
                            <dd className="text-right text-base font-semibold text-gray-800 dark:text-white/90">{formatMoney(totales.saldo ?? totales.total)}</dd>
                            {totales.situacion === 'con_saldo_a_favor' && (
                                <>
                                    <dt className="text-warning-600 dark:text-orange-400">Saldo a favor del cliente</dt>
                                    <dd className="text-right font-semibold text-warning-600 dark:text-orange-400">{formatMoney(totales.saldo_a_favor ?? '0')}</dd>
                                </>
                            )}
                        </>
                    )}
                </dl>
            </div>

            {esFactura && !esBorrador && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Cobros</h3>
                        {puedeCobrar && (
                            <Button variant="outline" size="sm" onClick={() => setCobroAbierto(true)}>
                                Nuevo cobro
                            </Button>
                        )}
                    </div>
                    <InvoicePaymentsTable
                        cobros={cobros}
                        puedeReversar={puedeAnular}
                        onReversar={(cobro) => setAccionConMotivo({ tipo: 'reversar', cobro })}
                        onVerRecibo={
                            puedeImprimir
                                ? (cobro) => void download(`/facturacion/cobros/${cobro.id}/recibo/pdf`, `recibo-cobro-${cobro.id}.pdf`)
                                : undefined
                        }
                    />
                </div>
            )}

            {!esBorrador && puedeAnular && (
                <InvoiceNoteForm
                    isOpen={notaAbierta}
                    documento={documento}
                    onClose={() => setNotaAbierta(false)}
                    onEmitida={() => {
                        setNotaAbierta(false);
                        informarExito('Nota emitida con su número consecutivo.');
                    }}
                    onError={(texto) => {
                        setNotaAbierta(false);
                        setMensaje(null);
                        setErrorAccion(texto);
                    }}
                />
            )}

            {esFactura && (
                <InvoicePaymentModal
                    isOpen={cobroAbierto}
                    documentoId={documento.id}
                    saldo={totales.saldo ?? totales.total}
                    onClose={() => setCobroAbierto(false)}
                    onRegistrado={(texto) => {
                        setCobroAbierto(false);
                        informarExito(texto);
                    }}
                    onError={(texto) => {
                        setCobroAbierto(false);
                        setMensaje(null);
                        setErrorAccion(texto);
                    }}
                />
            )}

            <MotivoModal
                isOpen={accionConMotivo !== null}
                title={accionConMotivo?.tipo === 'reversar' ? 'Reversar cobro' : 'Anular factura'}
                description={
                    accionConMotivo?.tipo === 'reversar'
                        ? 'La reversa compensa el cobro completo y anula su asiento. El cobro original no se borra.'
                        : 'La anulación emite una nota crédito por el saldo de la factura. La factura conserva su número y la nota queda asentada y enlazada a ella.'
                }
                confirmLabel={accionConMotivo?.tipo === 'reversar' ? 'Reversar cobro' : 'Anular factura'}
                isSubmitting={enviando}
                onClose={() => setAccionConMotivo(null)}
                onConfirm={(motivo) => void confirmarAccionConMotivo(motivo)}
            />

            <ConfirmModal
                isOpen={confirmarEliminar}
                onClose={() => setConfirmarEliminar(false)}
                onConfirm={() => void eliminar()}
                variant="danger"
                title="Eliminar borrador"
                description="El borrador se elimina. Un documento emitido no se puede eliminar."
                confirmLabel="Eliminar"
                isConfirming={enviando}
            />
        </div>
    );
};

function toDraft(linea: { concepto: string; descripcion: string; valor: string }): InvoiceLineDraft {
    return { concepto: linea.concepto, descripcion: linea.descripcion, valor: linea.valor };
}
