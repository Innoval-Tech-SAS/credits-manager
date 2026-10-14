import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { ArrowRight } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { ClientFullSelect } from '../../clients/components/client-full-select';
import type { ApiClient } from '../../clients/types/api-client';
import { usePermission } from '../../auth/hooks/use-permission';
import { ActionButton } from '@ui/action-button/components/action-button';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { DataTable } from '@ui/table/components/data-table';
import { InvoiceNoteForm } from '../components/invoice-note-form';
import { ThirdPartyFiscalSummary } from '../components/third-party-fiscal-summary';
import type { ApiInvoice, ApiInvoiceDetail } from '../types/api-invoice';

const ETIQUETA_TIPO = { factura: 'Factura', nota_credito: 'Nota crédito', nota_debito: 'Nota débito' } as const;

export const CreateNotePage: FC = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const tipoInicial = searchParams.get('tipo') === 'nota_debito' ? 'nota_debito' : 'nota_credito';
    const tituloPagina = tipoInicial === 'nota_debito' ? 'Nueva nota débito' : 'Nueva nota crédito';
    const listaDestino = tipoInicial === 'nota_debito' ? '/facturacion/notas-debito' : '/facturacion/notas-credito';

    // Mismas reglas que `invoice-note-form.tsx`: una factura admite nota crédito o débito, una nota solo admite débito.
    const puedeNotaCredito = usePermission('facturacion', 'emitir_nota_credito');
    const puedeNotaDebito = usePermission('facturacion', 'emitir_nota_debito');
    const puedeAjustar = (documento: ApiInvoice) => (documento.tipo === 'factura' ? puedeNotaCredito || puedeNotaDebito : puedeNotaDebito);

    const [cliente, setCliente] = useState<ApiClient | null>(null);
    const [documentoId, setDocumentoId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const documentosParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), estado: 'emitido', per_page: '100' }).toString() : '';
    const { data: documentosData, isLoading: cargandoDocumentos } = useResource<PaginatedResponse<ApiInvoice>>(
        cliente ? `/facturacion/documentos?${documentosParams}` : null,
    );
    const documentos = (documentosData?.data ?? []).filter(puedeAjustar);

    const { data: documentoDetalle } = useResource<ApiInvoiceDetail>(documentoId ? `/facturacion/documentos/${documentoId}` : null);

    const seleccionarCliente = (seleccionado: ApiClient | null) => {
        setCliente(seleccionado);
        setDocumentoId(null);
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle={tituloPagina} />

            {error && <Alert variant="danger" title="No se pudo emitir la nota" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">1. Cliente</h3>
                <Label htmlFor="cliente">Cliente</Label>
                <ClientFullSelect id="cliente" selected={cliente} onSelect={seleccionarCliente} />
            </div>

            {cliente && (
                <>
                    <ThirdPartyFiscalSummary tercero={cliente.tercero} />

                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">2. Documento a ajustar</h3>
                        {cargandoDocumentos ? (
                            <div className="flex justify-center py-6">
                                <Spinner size="lg" />
                            </div>
                        ) : (
                            <DataTable
                                columns={[
                                    { key: 'tipo', label: 'Tipo', render: (row) => ETIQUETA_TIPO[row.tipo] },
                                    { key: 'numero', label: 'Número', render: (row) => row.numero ?? 'Borrador' },
                                    { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                                    {
                                        key: 'acciones',
                                        label: 'Acciones',
                                        render: (row) => (
                                            <ActionButton icon={<ArrowRight className="size-5" />} label="Elegir este documento" onClick={() => setDocumentoId(row.id)} />
                                        ),
                                    },
                                ]}
                                rows={documentos}
                                getRowKey={(row) => row.id}
                                emptyMessage={`${cliente.tercero.nombre} no tiene facturas ni notas emitidas para ajustar.`}
                            />
                        )}
                        {documentoId !== null && !documentoDetalle && (
                            <div className="mt-4 flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <Spinner size="sm" />
                                Cargando documento…
                            </div>
                        )}
                    </div>
                </>
            )}

            {documentoDetalle && (
                <InvoiceNoteForm
                    isOpen
                    documento={documentoDetalle.documento}
                    tipoInicial={tipoInicial}
                    onClose={() => setDocumentoId(null)}
                    onEmitida={(tipoEmitido) =>
                        navigate(listaDestino, {
                            replace: true,
                            state: {
                                message:
                                    tipoEmitido === 'nota_credito'
                                        ? 'Nota crédito solicitada. Queda pendiente de aprobación por otra persona.'
                                        : 'Nota emitida con su número consecutivo.',
                            },
                        })
                    }
                    onError={(texto) => {
                        setDocumentoId(null);
                        setError(texto);
                    }}
                />
            )}
        </div>
    );
};
