import type { FC } from 'react';
import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { ClientFullSelect } from '../../clients/components/client-full-select';
import type { ApiClient } from '../../clients/types/api-client';
import type { ApiCredit } from '../../credits/types/api-credit';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { FieldSkeleton } from '@ui/skeleton/components/field-skeleton';
import { SkeletonStatus } from '@ui/skeleton/components/skeleton-status';
import { DataTable } from '@ui/table/components/data-table';
import { InvoiceLinesForm } from '../components/invoice-lines-form';
import { ThirdPartyFiscalSummary } from '../components/third-party-fiscal-summary';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { ApiInvoice } from '../types/api-invoice';

export const CreateInvoicePage: FC = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { sendRequest, isLoading } = useSendRequest();
    const { sendRequest: sendRequestInicial } = useSendRequest();

    const [cliente, setCliente] = useState<ApiClient | null>(null);
    const [creditoId, setCreditoId] = useState('');
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [fechaVencimiento, setFechaVencimiento] = useState('');
    const [lineas, setLineas] = useState<InvoiceLineDraft[]>([]);
    const [error, setError] = useState<string | null>(null);

    // El enlace "Facturar" desde el detalle de un crédito llega con `?credito=`: se resuelve su cliente antes de mostrar el paso 1.
    useEffect(() => {
        const creditoInicialId = searchParams.get('credito');

        if (!creditoInicialId) {
            return;
        }

        sendRequestInicial<{ credito: ApiCredit }>(`/creditos/${creditoInicialId}`)
            .then((respuesta) => {
                if (respuesta.credito.cliente) {
                    setCliente(respuesta.credito.cliente);
                }
                setCreditoId(creditoInicialId);
            })
            .catch(() => {});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const creditosParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), per_page: '100' }).toString() : '';
    const { data: creditosData, isLoading: cargandoCreditos } = useResource<PaginatedResponse<ApiCredit>>(cliente ? `/creditos?${creditosParams}` : null);
    const creditos = (creditosData?.data ?? []).filter((credito) => ['desembolsado', 'pagado'].includes(credito.estado));
    const credito = creditos.find((c) => String(c.id) === creditoId) ?? null;

    const pendientesParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), estado: 'emitido', con_saldo: '1', per_page: '20' }).toString() : '';
    const { data: pendientesData } = useResource<PaginatedResponse<ApiInvoice>>(cliente ? `/facturacion/documentos?${pendientesParams}` : null);
    const pendientes = (pendientesData?.data ?? []).filter((documento) => documento.tipo === 'factura' && parseFloat(documento.saldo ?? '0') > 0);

    const seleccionarCliente = (seleccionado: ApiClient | null) => {
        setCliente(seleccionado);
        setCreditoId('');
        setLineas([]);
        setFechaVencimiento('');
    };

    const seleccionarCredito = (valor: string) => {
        setCreditoId(valor);
        setLineas([]);
        setFechaVencimiento('');
    };

    const guardar = async () => {
        setError(null);

        try {
            const respuesta = await sendRequest<{ documento: ApiInvoice }>('/facturacion/documentos', {
                method: 'POST',
                body: {
                    cliente_id: cliente?.id,
                    credito_id: Number(creditoId),
                    fecha,
                    fecha_vencimiento: fechaVencimiento || null,
                    lineas: lineas.map((linea) => ({
                        tipo_concepto: linea.tipo_concepto,
                        cuota_id: linea.cuota_id ?? null,
                        concepto: linea.concepto,
                        descripcion: linea.descripcion,
                        valor: linea.valor,
                    })),
                },
            });

            navigate(`/facturacion/${respuesta.documento.id}`, { replace: true, state: { message: 'Factura creada en borrador. Revísala y emítela cuando esté lista.' } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const puedeGuardar = cliente !== null && creditoId !== '' && lineas.length > 0 && lineas.every((linea) => linea.tipo_concepto && linea.valor);
    const total = lineas.reduce((acumulado, linea) => acumulado + (parseFloat(linea.valor) || 0), 0);

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Nueva factura" />

            {error && <Alert variant="danger" title="No se pudo crear la factura" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">1. Cliente</h3>
                <Label htmlFor="cliente">Cliente</Label>
                <ClientFullSelect id="cliente" selected={cliente} onSelect={seleccionarCliente} />
            </div>

            {cliente && (
                <>
                    <ThirdPartyFiscalSummary tercero={cliente.tercero} />

                    {pendientes.length > 0 && (
                        <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                            <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Facturas pendientes de {cliente.tercero.nombre}</h3>
                            <DataTable
                                columns={[
                                    { key: 'numero', label: 'Número', render: (row) => row.numero ?? 'Borrador' },
                                    { key: 'fecha', label: 'Fecha', render: (row) => formatDateEs(row.fecha) },
                                    { key: 'saldo', label: 'Saldo', render: (row) => formatMoney(row.saldo ?? '0') },
                                ]}
                                rows={pendientes}
                                getRowKey={(row) => row.id}
                                emptyMessage="Sin facturas pendientes."
                            />
                        </div>
                    )}

                    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">2. Crédito</h3>
                        {cargandoCreditos ? (
                            <SkeletonStatus className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <FieldSkeleton />
                            </SkeletonStatus>
                        ) : (
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="credito">Crédito desembolsado</Label>
                                    <Select
                                        id="credito"
                                        key={cliente.id}
                                        options={creditos.map((c) => ({
                                            value: String(c.id),
                                            label: `#${c.id} · Saldo ${formatMoney(c.saldo ?? '0')}`,
                                        }))}
                                        placeholder={creditos.length === 0 ? 'Este cliente no tiene créditos disponibles' : 'Seleccione un crédito...'}
                                        defaultValue={creditoId}
                                        onChange={seleccionarCredito}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="fecha">Fecha</Label>
                                    <DatePicker id="fecha" value={fecha} onChange={setFecha} />
                                </div>
                                <div>
                                    <Label htmlFor="fecha-vencimiento">Fecha de vencimiento (opcional)</Label>
                                    <DatePicker id="fecha-vencimiento" value={fechaVencimiento} onChange={setFechaVencimiento} />
                                </div>
                            </div>
                        )}
                    </div>
                </>
            )}

            {credito && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">3. Conceptos a facturar</h3>
                    {lineas.length === 0 && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Agrega las líneas a facturar (mora, otros gastos...). El interés corriente de una cuota se factura aparte, desde{' '}
                            <button type="button" className="font-medium text-brand-500 hover:underline" onClick={() => navigate('/facturacion/intereses-pagados/crear')}>
                                Facturar intereses pagados
                            </button>
                            .
                        </p>
                    )}

                    <InvoiceLinesForm lineas={lineas} onChange={setLineas} requiereTipoConcepto />

                    {lineas.length > 0 && (
                        <div className="mt-4 flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <span className="text-sm text-gray-500 dark:text-gray-400">Total a facturar</span>
                            <span className="text-base font-semibold text-gray-800 dark:text-white/90">{formatMoney(total)}</span>
                        </div>
                    )}
                </div>
            )}

            <div className="flex justify-end gap-3">
                <Button variant="outline" onClick={() => navigate('/facturacion')}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !puedeGuardar} onClick={() => void guardar()}>
                    {isLoading ? 'Guardando…' : 'Guardar borrador'}
                </Button>
            </div>
        </div>
    );
};
