import type { FC } from 'react';
import { useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router';
import { Download, Send, Trash2 } from 'lucide-react';

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
import { InvoiceNoteForm } from '../components/invoice-note-form';
import { InvoiceLinesForm } from '../components/invoice-lines-form';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { ApiInvoiceDetail } from '../types/api-invoice';

const ETIQUETA_TIPO = { factura: 'Factura', nota_credito: 'Nota crédito', nota_debito: 'Nota débito' } as const;

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

    const reportarError = (caught: unknown) => {
        setMensaje(null);
        setErrorAccion(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
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

    const { documento, totales } = data;
    const esBorrador = documento.estado === 'borrador';
    const tituloDocumento = documento.numero ?? `${ETIQUETA_TIPO[documento.tipo]} en borrador`;

    const emitir = async () => {
        try {
            await sendRequest(`/facturacion/documentos/${documento.id}/emitir`, { method: 'POST' });
            setErrorAccion(null);
            setMensaje('Factura emitida. Su número ya no cambia.');
            void refetch();
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
            setErrorAccion(null);
            setMensaje('Borrador actualizado.');
            void refetch();
        } catch (caught) {
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
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{tituloDocumento}</h2>
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
                        {!esBorrador && puedeImprimir && (
                            <Button
                                variant="outline"
                                startIcon={<Download className="size-4" />}
                                disabled={isDownloading}
                                onClick={() => void download(`/facturacion/documentos/${documento.id}/pdf`, `${documento.numero ?? 'documento'}.pdf`)}
                            >
                                {isDownloading ? 'Generando…' : 'Descargar PDF'}
                            </Button>
                        )}
                    </div>
                </div>

                {totales.anulada && (
                    <p className="mt-4 text-sm font-medium text-warning-600 dark:text-warning-400">Factura anulada por notas crédito.</p>
                )}
            </div>

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
                    />
                )}

                <dl className="mt-4 grid grid-cols-2 gap-2 text-sm md:max-w-md md:ml-auto">
                    <dt className="text-gray-500 dark:text-gray-400">Total</dt>
                    <dd className="text-right font-semibold text-gray-800 dark:text-white/90">{formatMoney(totales.total)}</dd>
                    {documento.tipo === 'factura' && (
                        <>
                            <dt className="text-gray-500 dark:text-gray-400">Notas crédito</dt>
                            <dd className="text-right text-gray-800 dark:text-white/90">{formatMoney(totales.total_nota_credito ?? '0')}</dd>
                            <dt className="text-gray-500 dark:text-gray-400">Notas débito</dt>
                            <dd className="text-right text-gray-800 dark:text-white/90">{formatMoney(totales.total_nota_debito ?? '0')}</dd>
                            <dt className="text-gray-500 dark:text-gray-400">Saldo</dt>
                            <dd className="text-right font-semibold text-gray-800 dark:text-white/90">{formatMoney(totales.saldo ?? totales.total)}</dd>
                        </>
                    )}
                </dl>
            </div>

            {!esBorrador && puedeAnular && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Emitir nota</h3>
                    <InvoiceNoteForm
                        documento={documento}
                        onEmitida={() => {
                            setErrorAccion(null);
                            setMensaje('Nota emitida con su número consecutivo.');
                            void refetch();
                        }}
                        onError={(texto) => {
                            setMensaje(null);
                            setErrorAccion(texto);
                        }}
                    />
                </div>
            )}

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
