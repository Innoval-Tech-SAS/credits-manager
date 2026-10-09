import type { FC } from 'react';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { Link2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import type { ApiCarteraDocument } from '../../accounting/types/api-cartera-document';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiAdvanceCrossing } from '../types/api-advance';

type Lado = 'proveedor' | 'cliente';

const LADO_OPTIONS = [
    { value: 'proveedor', label: 'Anticipo a proveedor → cuenta por pagar' },
    { value: 'cliente', label: 'Anticipo de cliente → cuenta por cobrar' },
];

/** Cruza un anticipo contra el documento real que llegó: `POST /anticipos/cruzar`. */
export const CrossAdvancePage: FC = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const { sendRequest, isLoading: isSubmitting } = useSendRequest();

    const mensajeInicial = (location.state as { message?: string } | null)?.message ?? null;
    const [mensaje, setMensaje] = useState(mensajeInicial);

    const [lado, setLado] = useState<Lado>('proveedor');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [anticipoId, setAnticipoId] = useState('');
    const [documentoId, setDocumentoId] = useState('');
    const [valor, setValor] = useState('');
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [cruceToken, setCruceToken] = useState(0);

    const naturalezaAnticipo = lado === 'proveedor' ? 'anticipo_proveedor' : 'anticipo_cliente';
    const naturalezaDocumento = lado === 'proveedor' ? 'por_pagar' : 'por_cobrar';

    const { data: anticiposData, refetch: refetchAnticipos } = useResource<{ data: ApiCarteraDocument[] }>(
        tercero ? `/cartera-terceros?naturaleza=${naturalezaAnticipo}&tercero_id=${tercero.id}&estado=pendiente&per_page=100` : null,
    );
    const { data: documentosData, refetch: refetchDocumentos } = useResource<{ data: ApiCarteraDocument[] }>(
        tercero ? `/cartera-terceros?naturaleza=${naturalezaDocumento}&tercero_id=${tercero.id}&estado=pendiente&per_page=100` : null,
    );
    const { data: tiposData } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');

    const anticipos = anticiposData?.data ?? [];
    const documentos = documentosData?.data ?? [];
    const tipos = tiposData?.data ?? [];

    const anticipoSeleccionado = anticipos.find((item) => String(item.id) === anticipoId) ?? null;
    const documentoSeleccionado = documentos.find((item) => String(item.id) === documentoId) ?? null;

    // Al cambiar de tercero o de lado, la selección anterior ya no aplica: se limpia aquí (no en un efecto) para
    // que no llegue a pintarse ni un instante con datos del tercero previo.
    const claveSeleccion = tercero ? `${tercero.id}-${lado}` : null;
    const [claveCargada, setClaveCargada] = useState<string | null>(null);
    if (claveSeleccion !== claveCargada) {
        setClaveCargada(claveSeleccion);
        if (anticipoId !== '' || documentoId !== '' || valor !== '') {
            setAnticipoId('');
            setDocumentoId('');
            setValor('');
        }
    }

    const seleccionarAnticipo = (id: string) => {
        setAnticipoId(id);
        const anticipo = anticipos.find((item) => String(item.id) === id);
        if (anticipo && documentoSeleccionado) {
            setValor(String(Math.min(Number(anticipo.saldo), Number(documentoSeleccionado.saldo))));
        }
    };

    const seleccionarDocumento = (id: string) => {
        setDocumentoId(id);
        const documento = documentos.find((item) => String(item.id) === id);
        if (anticipoSeleccionado && documento) {
            setValor(String(Math.min(Number(anticipoSeleccionado.saldo), Number(documento.saldo))));
        }
    };

    const valorValido =
        anticipoSeleccionado !== null &&
        documentoSeleccionado !== null &&
        Number(valor) > 0 &&
        Number(valor) <= Number(anticipoSeleccionado.saldo) &&
        Number(valor) <= Number(documentoSeleccionado.saldo);

    const formularioValido = valorValido && fecha !== '' && tipoComprobanteId !== '';

    const cruzar = async () => {
        if (!anticipoSeleccionado || !documentoSeleccionado) {
            return;
        }

        setError(null);
        setMensaje(null);

        try {
            const respuesta = await sendRequest<ApiAdvanceCrossing>('/anticipos/cruzar', {
                method: 'POST',
                body: {
                    anticipo_id: anticipoSeleccionado.id,
                    documento_id: documentoSeleccionado.id,
                    valor,
                    fecha,
                    tipo_comprobante_id: Number(tipoComprobanteId),
                },
            });

            setMensaje(
                `Cruzados ${formatMoney(valor)} entre ${respuesta.anticipo.numero_documento} y ${respuesta.documento.numero_documento}.`,
            );
            setAnticipoId('');
            setDocumentoId('');
            setValor('');
            setCruceToken((token) => token + 1);
            refetchAnticipos();
            refetchDocumentos();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Cruzar anticipo" />

            {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}
            {error && <Alert variant="danger" title="No se pudo cruzar el anticipo" message={error} onDismiss={() => setError(null)} />}

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="lado">Lado</Label>
                        <Select options={LADO_OPTIONS} defaultValue={lado} onChange={(valor) => setLado(valor as Lado)} />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="tercero">Tercero</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
                    </div>
                </div>
            </div>

            {tercero && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="anticipo">Anticipo pendiente</Label>
                            {anticipos.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">Este tercero no tiene anticipos pendientes.</p>
                            ) : (
                                <Select
                                    key={`${claveSeleccion ?? 'none'}-${cruceToken}`}
                                    options={anticipos.map((item) => ({ value: String(item.id), label: `${item.numero_documento} — saldo ${formatMoney(item.saldo)}` }))}
                                    placeholder="Selecciona el anticipo"
                                    onChange={seleccionarAnticipo}
                                />
                            )}
                        </div>

                        <div>
                            <Label htmlFor="documento">{lado === 'proveedor' ? 'Cuenta por pagar pendiente' : 'Cuenta por cobrar pendiente'}</Label>
                            {documentos.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">Este tercero no tiene documentos pendientes.</p>
                            ) : (
                                <Select
                                    key={`${claveSeleccion ?? 'none'}-${cruceToken}`}
                                    options={documentos.map((item) => ({ value: String(item.id), label: `${item.numero_documento} — saldo ${formatMoney(item.saldo)}` }))}
                                    placeholder="Selecciona el documento"
                                    onChange={seleccionarDocumento}
                                />
                            )}
                        </div>
                    </div>

                    {anticipoSeleccionado && documentoSeleccionado && (
                        <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <Label htmlFor="valor">Valor a cruzar</Label>
                                <Input
                                    id="valor"
                                    type="number"
                                    min="0.01"
                                    step={0.01}
                                    max={String(Math.min(Number(anticipoSeleccionado.saldo), Number(documentoSeleccionado.saldo)))}
                                    value={valor}
                                    error={valor !== '' && !valorValido}
                                    hint={valor !== '' && !valorValido ? 'Debe ser mayor que cero y no superar el saldo del anticipo ni del documento.' : undefined}
                                    onChange={(event) => setValor(event.target.value)}
                                />
                            </div>

                            <div>
                                <Label htmlFor="fecha">Fecha</Label>
                                <DatePicker id="fecha" value={fecha} onChange={setFecha} required />
                            </div>

                            <div>
                                <Label htmlFor="tipo-comprobante">Tipo de comprobante</Label>
                                <Select
                                    options={tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }))}
                                    placeholder="Selecciona el tipo"
                                    onChange={setTipoComprobanteId}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={() => navigate('/contabilidad/anticipos/crear')} disabled={isSubmitting}>
                    Registrar otro anticipo
                </Button>
                <Button
                    disabled={!formularioValido || isSubmitting}
                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : <Link2 className="size-4" />}
                    onClick={() => void cruzar()}
                >
                    {isSubmitting ? 'Cruzando…' : 'Cruzar anticipo'}
                </Button>
            </div>
        </div>
    );
};
