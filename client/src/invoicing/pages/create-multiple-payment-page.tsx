import type { FC } from 'react';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { Send } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { ClientSelector } from '../../clients/components/client-selector';
import type { ApiClient } from '../../clients/types/api-client';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Checkbox } from '@ui/forms/components/checkbox';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { PAYMENT_METHOD_OPTIONS } from '../constants/payment-methods';
import type { ApiInvoice, ApiInvoicePaymentMethod } from '../types/api-invoice';

type FacturaPendiente = { factura: ApiInvoice; saldo: string };

/** Un recibo que cruza varias facturas del mismo cliente: cada una conserva su propio asiento (`POST /facturacion/cobros`), solo se bloquean y se cobran juntas. */
export const CreateMultiplePaymentPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading: isSubmitting } = useSendRequest();
    const { sendRequest: fetchDetalle } = useSendRequest();

    const [cliente, setCliente] = useState<ApiClient | null>(null);
    const [pendientes, setPendientes] = useState<FacturaPendiente[]>([]);
    const [seleccion, setSeleccion] = useState<Record<number, string>>({});

    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [medio, setMedio] = useState<ApiInvoicePaymentMethod>('transferencia');
    const [referencia, setReferencia] = useState('');
    const [observacion, setObservacion] = useState('');
    const [error, setError] = useState<string | null>(null);

    const { data: listaData } = useResource<{ data: ApiInvoice[] }>(
        cliente ? `/facturacion/documentos?cliente_id=${cliente.id}&estado=emitido&tipo=factura&per_page=100` : null,
    );

    // Lista en la que ya se terminó de pedir el saldo de cada factura (una por una, acotado al cliente elegido —
    // no es el N+1 de una tabla grande). Mientras no coincida con `listaData`, seguimos cargando.
    const [facturasCargadasPara, setFacturasCargadasPara] = useState<{ data: ApiInvoice[] } | null>(null);
    const isLoadingPendientes = cliente !== null && listaData !== null && facturasCargadasPara !== listaData;

    if (listaData !== facturasCargadasPara && (pendientes.length > 0 || Object.keys(seleccion).length > 0)) {
        setPendientes([]);
        setSeleccion({});
    }

    useEffect(() => {
        if (!listaData) {
            return;
        }

        let cancelado = false;

        Promise.all(
            listaData.data.map((factura) =>
                fetchDetalle<{ totales: { saldo?: string } }>(`/facturacion/documentos/${factura.id}`).then((detalle) => ({
                    factura,
                    saldo: detalle.totales.saldo ?? '0',
                })),
            ),
        ).then((resultados) => {
            if (!cancelado) {
                setPendientes(resultados.filter((item) => parseFloat(item.saldo) > 0));
                setFacturasCargadasPara(listaData);
            }
        });

        return () => {
            cancelado = true;
        };
    }, [listaData, fetchDetalle]);

    const seleccionar = (facturaId: number, saldo: string, marcado: boolean) => {
        setSeleccion((anterior) => {
            const copia = { ...anterior };
            if (marcado) {
                copia[facturaId] = saldo;
            } else {
                delete copia[facturaId];
            }
            return copia;
        });
    };

    const actualizarValor = (facturaId: number, valor: string) => {
        setSeleccion((anterior) => ({ ...anterior, [facturaId]: valor }));
    };

    const cantidadSeleccionada = Object.keys(seleccion).length;
    const totalSeleccionado = Object.values(seleccion).reduce((acumulado, valor) => acumulado + (parseFloat(valor) || 0), 0);

    const valorValido = (facturaId: number, saldo: string) => {
        const valor = seleccion[facturaId];
        return valor !== undefined && valor.trim() !== '' && Number(valor) > 0 && Number(valor) <= Number(saldo);
    };

    const puedeEnviar =
        cliente !== null &&
        cantidadSeleccionada >= 2 &&
        fecha !== '' &&
        pendientes.every((item) => !(item.factura.id in seleccion) || valorValido(item.factura.id, item.saldo));

    const registrar = async () => {
        if (!cliente) {
            return;
        }

        setError(null);

        try {
            await sendRequest('/facturacion/cobros', {
                method: 'POST',
                body: {
                    tercero_id: cliente.tercero_id,
                    fecha,
                    medio,
                    referencia: referencia.trim() === '' ? null : referencia.trim(),
                    observacion: observacion.trim() === '' ? null : observacion.trim(),
                    aplicaciones: Object.entries(seleccion).map(([documentoId, valor]) => ({
                        documento_facturacion_id: Number(documentoId),
                        valor,
                    })),
                },
            });

            navigate('/facturacion', {
                replace: true,
                state: { message: `Cobro múltiple registrado sobre ${cantidadSeleccionada} facturas. Cada una quedó con su propio asiento.` },
            });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Cobro múltiple" />

            {error && <Alert variant="danger" title="No se pudo registrar el cobro" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <Label htmlFor="cliente">Cliente</Label>
                <ClientSelector id="cliente" selected={cliente} onSelect={setCliente} />
            </div>

            {cliente && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Facturas pendientes</h3>

                    {isLoadingPendientes && (
                        <div className="flex justify-center py-6">
                            <Spinner size="lg" />
                        </div>
                    )}

                    {!isLoadingPendientes && pendientes.length === 0 && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Este cliente no tiene facturas con saldo pendiente.</p>
                    )}

                    {!isLoadingPendientes && pendientes.length > 0 && (
                        <div className="flex flex-col gap-4">
                            {pendientes.map(({ factura, saldo }) => {
                                const marcada = factura.id in seleccion;

                                return (
                                    <div
                                        key={factura.id}
                                        className="grid grid-cols-1 gap-3 rounded-xl border border-gray-100 p-4 dark:border-gray-800 md:grid-cols-[auto_1fr_auto] md:items-center"
                                    >
                                        <Checkbox
                                            id={`factura-${factura.id}`}
                                            label={`${factura.numero ?? '—'} · ${formatDateEs(factura.fecha)}`}
                                            checked={marcada}
                                            onChange={(checked) => seleccionar(factura.id, saldo, checked)}
                                        />

                                        <p className="text-sm text-gray-500 dark:text-gray-400 md:text-right">
                                            Saldo pendiente: {formatMoney(saldo)}
                                        </p>

                                        <div className="md:w-40">
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step={0.01}
                                                max={saldo}
                                                disabled={!marcada}
                                                value={seleccion[factura.id] ?? ''}
                                                error={marcada && !valorValido(factura.id, saldo)}
                                                hint={marcada && !valorValido(factura.id, saldo) ? 'Debe ser mayor que cero y no superar el saldo.' : undefined}
                                                onChange={(event) => actualizarValor(factura.id, event.target.value)}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {cliente && pendientes.length > 0 && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Datos del recibo</h3>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="cobro-multiple-fecha">Fecha</Label>
                            <DatePicker id="cobro-multiple-fecha" value={fecha} onChange={setFecha} required />
                        </div>
                        <div>
                            <Label htmlFor="cobro-multiple-medio">Medio de pago</Label>
                            <Select id="cobro-multiple-medio" options={PAYMENT_METHOD_OPTIONS} defaultValue={medio} onChange={(valor) => setMedio(valor as ApiInvoicePaymentMethod)} />
                        </div>
                        <div>
                            <Label htmlFor="cobro-multiple-referencia">Referencia</Label>
                            <Input id="cobro-multiple-referencia" value={referencia} maxLength={60} onChange={(event) => setReferencia(event.target.value)} />
                        </div>
                        <div className="sm:col-span-2">
                            <Label htmlFor="cobro-multiple-observacion">Observación</Label>
                            <Input id="cobro-multiple-observacion" value={observacion} maxLength={500} onChange={(event) => setObservacion(event.target.value)} />
                        </div>
                    </div>

                    <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        {cantidadSeleccionada === 0
                            ? 'Marca al menos dos facturas para armar el recibo.'
                            : `${cantidadSeleccionada} ${cantidadSeleccionada === 1 ? 'factura marcada' : 'facturas marcadas'} — total ${formatMoney(totalSeleccionado)}`}
                    </p>
                </div>
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={() => navigate('/facturacion')} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button
                    disabled={!puedeEnviar || isSubmitting}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : <Send className="size-4" />}
                    onClick={() => void registrar()}
                >
                    {isSubmitting ? 'Registrando…' : 'Registrar cobro'}
                </Button>
            </div>
        </div>
    );
};
