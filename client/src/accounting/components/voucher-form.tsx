import type { FC, SubmitEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import type { ApiInvoice } from '../../invoicing/types/api-invoice';
import { VoucherFormLineRow } from './voucher-form-line-row';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { Plus, Save } from 'lucide-react';
import type { VoucherFormProps } from '../props/voucher-form-props';
import type { ApiVoucher } from '../types/api-voucher';
import type { VoucherFormLine } from '../types/voucher-form-line';

let nextLineKey = 1;

function nuevaLinea(tercero: ApiThirdParty | null = null, descripcion = ''): VoucherFormLine {
    return {
        key: nextLineKey++,
        cuenta_id: '',
        centro_costo_id: '',
        tercero,
        documento_cruce: '',
        fecha_vencimiento: '',
        descripcion,
        debito: '',
        credito: '',
    };
}

export const VoucherForm: FC<VoucherFormProps> = ({ tipos, cuentas, centrosCosto }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [consecutivo, setConsecutivo] = useState('');
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [concepto, setConcepto] = useState('');
    const [documentoSoporte, setDocumentoSoporte] = useState('');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [lineas, setLineas] = useState<VoucherFormLine[]>([nuevaLinea(), nuevaLinea()]);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data: facturasData, isLoading: isLoadingFacturas } = useResource<{ data: ApiInvoice[] }>(
        tercero ? `/facturacion/documentos?tipo=factura&estado=emitido&tercero_id=${tercero.id}&con_saldo=1&per_page=10` : null,
    );
    const facturas = facturasData?.data ?? [];

    const cuentaOptions = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));
    const centroCostoOptions = centrosCosto.map((centro) => ({ value: String(centro.id), label: `${centro.codigo} - ${centro.nombre}` }));
    const tipoOptions = tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));
    const tipoSeleccionado = tipos.find((tipo) => String(tipo.id) === tipoComprobanteId);
    const esNumeracionManual = tipoSeleccionado?.tipo_numeracion === 'manual';

    const totalDebito = lineas.reduce((total, linea) => total + (parseFloat(linea.debito) || 0), 0);
    const totalCredito = lineas.reduce((total, linea) => total + (parseFloat(linea.credito) || 0), 0);
    const cuadra = Math.round(totalDebito * 100) === Math.round(totalCredito * 100) && totalDebito > 0;

    const actualizarLinea = (key: number, cambios: Partial<VoucherFormLine>) => {
        setLineas((actuales) => actuales.map((linea) => (linea.key === key ? { ...linea, ...cambios } : linea)));
    };

    const seleccionarTercero = (nuevoTercero: ApiThirdParty) => {
        setTercero(nuevoTercero);
        // Solo precarga las líneas que todavía no tienen un tercero propio: no pisa lo que el usuario ya eligió.
        setLineas((actuales) => actuales.map((linea) => (linea.tercero ? linea : { ...linea, tercero: nuevoTercero })));
    };

    const descripcionSugeridaRef = useRef('');

    useEffect(() => {
        const sugerida = documentoSoporte && concepto ? `${documentoSoporte} - ${concepto}` : '';
        const anterior = descripcionSugeridaRef.current;
        if (sugerida === anterior) return;

        // Solo pisa las líneas cuya descripción seguía vacía o coincidía con la sugerencia anterior: no borra lo que el usuario ya escribió.
        // `anterior` se captura en una constante (no se lee `descripcionSugeridaRef.current` dentro del updater) porque React ejecuta
        // esta función más tarde, cuando el ref ya habría sido mutado a `sugerida` por la línea de abajo.
        setLineas((actuales) =>
            actuales.map((linea) =>
                linea.descripcion === '' || linea.descripcion === anterior ? { ...linea, descripcion: sugerida } : linea,
            ),
        );
        descripcionSugeridaRef.current = sugerida;
    }, [documentoSoporte, concepto]);

    const agregarLinea = () => setLineas((actuales) => [...actuales, nuevaLinea(tercero, descripcionSugeridaRef.current)]);

    const eliminarLinea = (key: number) => {
        setLineas((actuales) => (actuales.length > 2 ? actuales.filter((linea) => linea.key !== key) : actuales));
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        const lineasValidas = lineas.filter((linea) => linea.cuenta_id);
        if (lineasValidas.length === 0) {
            setError('Agrega al menos una línea con cuenta seleccionada.');
            return;
        }

        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ comprobante: ApiVoucher }>('/contabilidad/comprobantes', {
                method: 'POST',
                body: {
                    tipo_comprobante_id: Number(tipoComprobanteId),
                    consecutivo: esNumeracionManual ? Number(consecutivo) || null : null,
                    fecha,
                    concepto,
                    documento_soporte: documentoSoporte || null,
                    tercero_id: tercero?.id ?? null,
                    lineas: lineasValidas.map((linea) => ({
                        cuenta_id: Number(linea.cuenta_id),
                        centro_costo_id: linea.centro_costo_id ? Number(linea.centro_costo_id) : null,
                        tercero_id: linea.tercero?.id ?? null,
                        documento_cruce: linea.documento_cruce || null,
                        fecha_vencimiento: linea.fecha_vencimiento || null,
                        descripcion: linea.descripcion || null,
                        debito: parseFloat(linea.debito) || 0,
                        credito: parseFloat(linea.credito) || 0,
                    })),
                },
            });

            navigate(`/contabilidad/comprobantes/${respuesta.comprobante.id}`, {
                replace: true,
                state: { message: 'Comprobante creado en borrador. Otro usuario debe revisarlo y contabilizarlo.' },
            });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <Label htmlFor="tipo-comprobante">Tipo de comprobante</Label>
                        <Select id="tipo-comprobante" options={tipoOptions} defaultValue={tipoComprobanteId} onChange={setTipoComprobanteId} />
                    </div>

                    {esNumeracionManual && (
                        <div>
                            <Label htmlFor="consecutivo">Consecutivo</Label>
                            <Input
                                id="consecutivo"
                                name="consecutivo"
                                type="number"
                                min="1"
                                required
                                value={consecutivo}
                                onChange={(event) => setConsecutivo(event.target.value)}
                            />
                        </div>
                    )}

                    <div>
                        <Label htmlFor="fecha">Fecha</Label>
                        <DatePicker id="fecha" name="fecha" required value={fecha} onChange={setFecha} />
                    </div>

                    <div>
                        <Label htmlFor="tercero">Tercero</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={seleccionarTercero} onClear={() => setTercero(null)} />
                    </div>

                    <div>
                        <Label htmlFor="documento-soporte">Documento soporte</Label>
                        <Input
                            id="documento-soporte"
                            name="documento-soporte"
                            value={documentoSoporte}
                            onChange={(event) => setDocumentoSoporte(event.target.value)}
                        />
                    </div>
                </div>

                {tercero && (isLoadingFacturas || facturas.length > 0) && (
                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                        <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Facturas de {tercero.nombre}
                        </p>

                        {isLoadingFacturas && <p className="text-sm text-gray-500 dark:text-gray-400">Buscando facturas…</p>}

                        {!isLoadingFacturas && (
                            <div className="flex flex-wrap gap-2">
                                {facturas.map((factura) => (
                                    <button
                                        key={factura.id}
                                        type="button"
                                        onClick={() => setDocumentoSoporte(factura.numero ?? '')}
                                        title="Usar como documento soporte"
                                        className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-brand-800 dark:hover:bg-brand-500/10"
                                    >
                                        <span>{factura.numero ?? '—'}</span>
                                        <span className="text-gray-400 dark:text-gray-500">·</span>
                                        <span className="text-gray-500 dark:text-gray-400">{formatDateEs(factura.fecha)}</span>
                                        {factura.saldo !== undefined && (
                                            <>
                                                <span className="text-gray-400 dark:text-gray-500">·</span>
                                                <span className="text-gray-500 dark:text-gray-400">{formatMoney(factura.saldo)}</span>
                                            </>
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <Label htmlFor="concepto">Concepto</Label>
                    <Input id="concepto" name="concepto" required value={concepto} onChange={(event) => setConcepto(event.target.value)} />
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <p className="text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Líneas (débito = crédito, obligatorio para contabilizar)
                        </p>
                        <Button iconOnly startIcon={<Plus className="size-4" />} type="button" variant="outline" size="sm" onClick={agregarLinea} className="shrink-0 whitespace-nowrap">
                            Agregar línea
                        </Button>
                    </div>

                    <div className="custom-scrollbar min-h-80 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table className="w-full min-w-280 text-left text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-800">
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Cuenta</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Centro de costo</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Tercero</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Doc. cruce</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Vence</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Descripción</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Débito</th>
                                    <th className="px-4 py-4 text-xs font-medium text-gray-500 dark:text-gray-400">Crédito</th>
                                    <th className="px-4 py-4" />
                                </tr>
                            </thead>
                            <tbody>
                                {lineas.map((linea) => (
                                    <VoucherFormLineRow
                                        key={linea.key}
                                        linea={linea}
                                        cuentas={cuentas}
                                        cuentaOptions={cuentaOptions}
                                        centroCostoOptions={centroCostoOptions}
                                        terceroComprobante={tercero}
                                        puedeEliminar={lineas.length > 2}
                                        onActualizar={(cambios) => actualizarLinea(linea.key, cambios)}
                                        onEliminar={() => eliminarLinea(linea.key)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div
                        className={`mt-3 rounded-lg px-4 py-2 text-sm font-medium ${
                            cuadra
                                ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                : 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400'
                        }`}
                    >
                        Débitos: {totalDebito.toFixed(2)} — Créditos: {totalCredito.toFixed(2)}{' '}
                        {cuadra ? '(cuadra)' : '(el comprobante debe cuadrar para poder contabilizarse)'}
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !tipoComprobanteId || !tercero || (esNumeracionManual && !consecutivo)}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting ? 'Contabilizando…' : 'Contabilizar comprobante'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
