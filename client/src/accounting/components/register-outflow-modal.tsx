import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
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

/** Paga por completo un documento por pagar: crea el egreso, lo cruza y lo contabiliza en el servidor. */
export const RegisterOutflowModal: FC<RegisterOutflowModalProps> = ({ isOpen, terceroId, documentoTerceroId, saldo, onClose, onRegistrado, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
        {isOpen && (
            <FormularioEgreso
                terceroId={terceroId}
                documentoTerceroId={documentoTerceroId}
                saldo={saldo}
                onClose={onClose}
                onRegistrado={onRegistrado}
                onError={onError}
            />
        )}
    </Modal>
);

type FormularioEgresoProps = Omit<RegisterOutflowModalProps, 'isOpen'>;

const FormularioEgreso: FC<FormularioEgresoProps> = ({ terceroId, documentoTerceroId, saldo, onClose, onRegistrado, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const { data: tiposData, isLoading: isLoadingTipos } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);
    const tipos = tiposData?.data ?? [];

    const [fecha, setFecha] = useState(hoyBogota());
    const [medioPago, setMedioPago] = useState<ApiOutflowPaymentMethod>('transferencia');
    const [cuentaCajaId, setCuentaCajaId] = useState('');
    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [concepto, setConcepto] = useState('');

    const formularioValido = fecha !== '' && cuentaCajaId !== '' && tipoComprobanteId !== '' && concepto.trim() !== '';
    const cargandoListas = isLoadingCuentas || isLoadingTipos;

    const registrar = async () => {
        try {
            const respuesta = await sendRequest<{ egreso: ApiOutflow }>('/egresos', {
                method: 'POST',
                body: {
                    tercero_id: terceroId,
                    tipo_comprobante_id: Number(tipoComprobanteId),
                    fecha,
                    medio_pago: medioPago,
                    cuenta_caja_id: Number(cuentaCajaId),
                    concepto: concepto.trim(),
                    aplicaciones: [{ documento_tercero_id: documentoTerceroId, valor: saldo }],
                },
            });
            onRegistrado(`Egreso ${respuesta.egreso.numero} registrado. El documento quedó cruzado y contabilizado.`);
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar pago</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">Valor a pagar: {formatMoney(saldo)}</p>
            </div>

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
                            options={OUTFLOW_PAYMENT_METHOD_OPTIONS}
                            defaultValue={medioPago}
                            onChange={(valorSeleccionado) => setMedioPago(valorSeleccionado as ApiOutflowPaymentMethod)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="egreso-cuenta-caja">Cuenta de caja o banco</Label>
                        <Select
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Selecciona la cuenta"
                            onChange={setCuentaCajaId}
                        />
                    </div>
                    <div>
                        <Label htmlFor="egreso-tipo-comprobante">Tipo de comprobante</Label>
                        <Select
                            options={tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }))}
                            placeholder="Selecciona el tipo"
                            onChange={setTipoComprobanteId}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <Label htmlFor="egreso-concepto">Concepto</Label>
                        <Input id="egreso-concepto" value={concepto} maxLength={255} onChange={(event) => setConcepto(event.target.value)} />
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
