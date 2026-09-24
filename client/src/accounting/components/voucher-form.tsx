import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { ArrowRight, Plus, Trash2 } from 'lucide-react';
import type { VoucherFormProps } from '../props/voucher-form-props';
import type { ApiVoucher } from '../types/api-voucher';
import type { VoucherFormLine } from '../types/voucher-form-line';

let nextLineKey = 1;

function nuevaLinea(): VoucherFormLine {
    return { key: nextLineKey++, cuenta_id: '', descripcion: '', debito: '', credito: '' };
}

export const VoucherForm: FC<VoucherFormProps> = ({ tipos, cuentas }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [fecha, setFecha] = useState(() => new Date().toISOString().slice(0, 10));
    const [concepto, setConcepto] = useState('');
    const [documentoSoporte, setDocumentoSoporte] = useState('');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [lineas, setLineas] = useState<VoucherFormLine[]>([nuevaLinea(), nuevaLinea()]);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const cuentaOptions = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));
    const tipoOptions = tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const totalDebito = lineas.reduce((total, linea) => total + (parseFloat(linea.debito) || 0), 0);
    const totalCredito = lineas.reduce((total, linea) => total + (parseFloat(linea.credito) || 0), 0);
    const cuadra = Math.round(totalDebito * 100) === Math.round(totalCredito * 100) && totalDebito > 0;

    const actualizarLinea = (key: number, cambios: Partial<VoucherFormLine>) => {
        setLineas((actuales) => actuales.map((linea) => (linea.key === key ? { ...linea, ...cambios } : linea)));
    };

    const agregarLinea = () => setLineas((actuales) => [...actuales, nuevaLinea()]);

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
                    fecha,
                    concepto,
                    documento_soporte: documentoSoporte || null,
                    tercero_id: tercero?.id ?? null,
                    lineas: lineasValidas.map((linea) => ({
                        cuenta_id: Number(linea.cuenta_id),
                        descripcion: linea.descripcion || null,
                        debito: parseFloat(linea.debito) || 0,
                        credito: parseFloat(linea.credito) || 0,
                    })),
                },
            });

            navigate(`/contabilidad/comprobantes/${respuesta.comprobante.id}`, {
                replace: true,
                state: { message: 'Comprobante creado y contabilizado.' },
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
                        <Select options={tipoOptions} defaultValue={tipoComprobanteId} onChange={setTipoComprobanteId} />
                    </div>

                    <div>
                        <Label htmlFor="fecha">Fecha</Label>
                        <DatePicker id="fecha" name="fecha" required value={fecha} onChange={setFecha} />
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

                    <div>
                        <Label htmlFor="tercero">Tercero (opcional)</Label>
                        <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
                    </div>
                </div>

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

                    <div className="custom-scrollbar overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table className="w-full min-w-160 text-left text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-800">
                                    <th className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">Cuenta</th>
                                    <th className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">Descripción</th>
                                    <th className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">Débito</th>
                                    <th className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">Crédito</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {lineas.map((linea) => (
                                    <tr key={linea.key} className="border-b border-gray-100 dark:border-gray-800">
                                        <td className="px-4 py-2">
                                            <Select
                                                options={cuentaOptions}
                                                placeholder="--"
                                                defaultValue={linea.cuenta_id}
                                                onChange={(value) => actualizarLinea(linea.key, { cuenta_id: value })}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input
                                                value={linea.descripcion}
                                                onChange={(event) => actualizarLinea(linea.key, { descripcion: event.target.value })}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input
                                                type="number"
                                                step={0.01}
                                                value={linea.debito}
                                                onChange={(event) => actualizarLinea(linea.key, { debito: event.target.value })}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input
                                                type="number"
                                                step={0.01}
                                                value={linea.credito}
                                                onChange={(event) => actualizarLinea(linea.key, { credito: event.target.value })}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <button
                                                type="button"
                                                onClick={() => eliminarLinea(linea.key)}
                                                disabled={lineas.length <= 2}
                                                className="text-gray-400 hover:text-error-500 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
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
                        disabled={isSubmitting || !tipoComprobanteId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <ArrowRight className="size-4" />}
                    >
                        {isSubmitting ? 'Contabilizando…' : 'Contabilizar comprobante'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
