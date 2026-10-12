import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { ACCEPTED_DOCUMENT_TYPES } from '../../shared/constants/accepted-document-types';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiAccount } from '../types/api-account';
import type { ApiVoucherType } from '../types/api-voucher-type';
import { OUTFLOW_PAYMENT_METHOD_OPTIONS } from '../constants/outflow-payment-methods';
import type { ApiOutflow, ApiOutflowPaymentMethod } from '../types/api-outflow';
import type { RegisterOutflowModalProps } from '../props/register-outflow-modal-props';

const hoyBogota = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota' }).format(new Date());

/**
 * Paga uno o varios documentos por pagar del mismo tercero: crea un solo egreso con una línea de crédito por cada
 * cuenta involucrada (`EgresoService` ya agrupa así), lo cruza contra cada documento y lo contabiliza en el servidor.
 */
export const RegisterOutflowModal: FC<RegisterOutflowModalProps> = ({ isOpen, terceroId, documentos, onClose, onRegistrado, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
        {isOpen && <FormularioEgreso terceroId={terceroId} documentos={documentos} onClose={onClose} onRegistrado={onRegistrado} onError={onError} />}
    </Modal>
);

type FormularioEgresoProps = Omit<RegisterOutflowModalProps, 'isOpen'>;

const FormularioEgreso: FC<FormularioEgresoProps> = ({ terceroId, documentos, onClose, onRegistrado, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData, isLoading: isLoadingTipos } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);
    const tipos = tiposData?.data ?? [];

    const [valores, setValores] = useState<Record<number, string>>(() => Object.fromEntries(documentos.map((documento) => [documento.id, documento.saldo])));
    const [descuentos, setDescuentos] = useState<Record<number, string>>(() => Object.fromEntries(documentos.map((documento) => [documento.id, ''])));
    const [fecha, setFecha] = useState(hoyBogota());
    const [medioPago, setMedioPago] = useState<ApiOutflowPaymentMethod>('transferencia');
    const [cuentaCajaId, setCuentaCajaId] = useState('');
    const [cuentaDescuentoId, setCuentaDescuentoId] = useState('');
    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [concepto, setConcepto] = useState('');
    const [soporte, setSoporte] = useState<File | null>(null);

    const totalAPagar = documentos.reduce((acumulado, documento) => acumulado + (parseFloat(valores[documento.id] ?? '0') || 0), 0);
    const totalDescuento = documentos.reduce((acumulado, documento) => acumulado + (parseFloat(descuentos[documento.id] ?? '0') || 0), 0);
    const hayDescuento = totalDescuento > 0;

    const valorValido = (documento: (typeof documentos)[number]) => {
        const valor = valores[documento.id];
        return valor !== undefined && valor.trim() !== '' && Number(valor) > 0 && Number(valor) <= Number(documento.saldo);
    };

    const descuentoValido = (documento: (typeof documentos)[number]) => {
        const descuento = descuentos[documento.id];
        if (descuento === undefined || descuento.trim() === '') return true;
        return Number(descuento) >= 0 && Number(descuento) < Number(valores[documento.id] ?? '0');
    };

    const formularioValido =
        fecha !== '' &&
        cuentaCajaId !== '' &&
        tipoComprobanteId !== '' &&
        concepto.trim() !== '' &&
        soporte !== null &&
        documentos.every(valorValido) &&
        documentos.every(descuentoValido) &&
        (!hayDescuento || cuentaDescuentoId !== '');
    const cargandoListas = isLoadingCuentas || isLoadingTipos;

    const registrar = async () => {
        try {
            const formData = new FormData();
            formData.append('tercero_id', String(terceroId));
            formData.append('tipo_comprobante_id', tipoComprobanteId);
            formData.append('fecha', fecha);
            formData.append('medio_pago', medioPago);
            formData.append('cuenta_caja_id', cuentaCajaId);
            if (hayDescuento) {
                formData.append('cuenta_descuento_id', cuentaDescuentoId);
            }
            formData.append('concepto', concepto.trim());
            formData.append(
                'aplicaciones',
                JSON.stringify(
                    documentos.map((documento) => ({
                        documento_tercero_id: documento.id,
                        valor: valores[documento.id],
                        descuento: descuentos[documento.id]?.trim() || '0',
                    })),
                ),
            );
            if (soporte) {
                formData.append('soporte', soporte);
            }

            const respuesta = await sendRequest<{ egreso: ApiOutflow }>('/egresos', { method: 'POST', body: formData });
            onRegistrado(
                documentos.length === 1
                    ? `Egreso ${respuesta.egreso.numero} registrado. El documento quedó cruzado y contabilizado.`
                    : `Egreso ${respuesta.egreso.numero} registrado sobre ${documentos.length} documentos.`,
            );
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar pago</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {documentos.length === 1 ? `Valor a pagar: ${formatMoney(documentos[0].saldo)}` : `${documentos.length} documentos — total ${formatMoney(totalAPagar)}`}
                </p>
            </div>

            {documentos.length > 1 ? (
                <div className="flex flex-col gap-3">
                    {documentos.map((documento) => (
                        <div
                            key={documento.id}
                            className="grid grid-cols-1 gap-3 rounded-xl border border-gray-100 p-3 dark:border-gray-800 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center"
                        >
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{documento.numero_documento}</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400 sm:text-right">Saldo: {formatMoney(documento.saldo)}</p>
                            <div className="sm:w-36">
                                <Label htmlFor={`egreso-valor-${documento.id}`}>Valor</Label>
                                <Input
                                    id={`egreso-valor-${documento.id}`}
                                    type="number"
                                    min="0.01"
                                    step={0.01}
                                    max={documento.saldo}
                                    value={valores[documento.id] ?? ''}
                                    error={!valorValido(documento)}
                                    onChange={(event) => setValores((anterior) => ({ ...anterior, [documento.id]: event.target.value }))}
                                />
                            </div>
                            <div className="sm:w-32">
                                <Label htmlFor={`egreso-descuento-${documento.id}`}>Descuento</Label>
                                <Input
                                    id={`egreso-descuento-${documento.id}`}
                                    type="number"
                                    min="0"
                                    step={0.01}
                                    value={descuentos[documento.id] ?? ''}
                                    error={!descuentoValido(documento)}
                                    onChange={(event) => setDescuentos((anterior) => ({ ...anterior, [documento.id]: event.target.value }))}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="sm:w-48">
                    <Label htmlFor={`egreso-descuento-${documentos[0].id}`}>Descuento por pronto pago (opcional)</Label>
                    <Input
                        id={`egreso-descuento-${documentos[0].id}`}
                        type="number"
                        min="0"
                        step={0.01}
                        value={descuentos[documentos[0].id] ?? ''}
                        error={!descuentoValido(documentos[0])}
                        onChange={(event) => setDescuentos((anterior) => ({ ...anterior, [documentos[0].id]: event.target.value }))}
                    />
                </div>
            )}

            {cargandoListas ? (
                <div className="flex justify-center py-8">
                    <Spinner size="lg" />
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="egreso-fecha">Fecha</Label>
                        <DatePicker id="egreso-fecha" value={fecha} onChange={setFecha} required />
                    </div>
                    <div>
                        <Label htmlFor="egreso-medio">Medio de pago</Label>
                        <Select
                            id="egreso-medio"
                            options={OUTFLOW_PAYMENT_METHOD_OPTIONS}
                            defaultValue={medioPago}
                            onChange={(valorSeleccionado) => setMedioPago(valorSeleccionado as ApiOutflowPaymentMethod)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="egreso-cuenta-caja">Cuenta de caja o banco</Label>
                        <Select
                            id="egreso-cuenta-caja"
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Selecciona la cuenta"
                            onChange={setCuentaCajaId}
                        />
                    </div>
                    <div>
                        <Label htmlFor="egreso-tipo-comprobante">Tipo de comprobante</Label>
                        <Select
                            id="egreso-tipo-comprobante"
                            options={tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }))}
                            placeholder="Selecciona el tipo"
                            onChange={setTipoComprobanteId}
                        />
                    </div>
                    {hayDescuento && (
                        <div>
                            <Label htmlFor="egreso-cuenta-descuento">Cuenta de descuentos comerciales condicionados</Label>
                            <Select
                                id="egreso-cuenta-descuento"
                                options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                                placeholder="Selecciona la cuenta"
                                onChange={setCuentaDescuentoId}
                            />
                        </div>
                    )}
                    <div className="sm:col-span-2">
                        <Label htmlFor="egreso-concepto">Concepto</Label>
                        <Input id="egreso-concepto" value={concepto} maxLength={255} onChange={(event) => setConcepto(event.target.value)} />
                    </div>
                    <div className="sm:col-span-2">
                        <Label htmlFor="egreso-soporte">Soporte (transferencia, cheque o planilla bancaria)</Label>
                        {soporte ? (
                            <div className="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                                <span className="text-gray-700 dark:text-gray-300">{soporte.name}</span>
                                <button type="button" className="text-error-600 hover:underline dark:text-error-400" onClick={() => setSoporte(null)}>
                                    Quitar
                                </button>
                            </div>
                        ) : (
                            <Dropzone id="egreso-soporte" accept={ACCEPTED_DOCUMENT_TYPES} hint="PDF, PNG, JPG o WEBP" onFileAccepted={setSoporte} />
                        )}
                    </div>
                </div>
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || cargandoListas || !formularioValido} onClick={() => void registrar()}>
                    {isLoading ? 'Registrando…' : 'Registrar egreso'}
                </Button>
            </div>
        </div>
    );
};
