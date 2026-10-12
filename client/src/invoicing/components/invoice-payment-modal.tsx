import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Modal } from '@ui/modal/components/modal';
import { PAYMENT_METHOD_OPTIONS } from '../constants/payment-methods';
import type { ApiInvoicePaymentMethod } from '../types/api-invoice';
import type { InvoicePaymentModalProps } from '../props/invoice-payment-modal-props';

const hoyBogota = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bogota' }).format(new Date());

/** Registra un cobro sobre la factura. El servidor valida el saldo; aquí solo se evita enviar lo evidentemente inválido. */
export const InvoicePaymentModal: FC<InvoicePaymentModalProps> = ({ isOpen, documentoId, saldo, onClose, onRegistrado, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
        {/* El formulario se monta al abrir: fecha, valor (= saldo) y demás campos parten de datos frescos. */}
        {isOpen && (
            <FormularioCobro documentoId={documentoId} saldo={saldo} onClose={onClose} onRegistrado={onRegistrado} onError={onError} />
        )}
    </Modal>
);

type FormularioCobroProps = Omit<InvoicePaymentModalProps, 'isOpen'>;

const FormularioCobro: FC<FormularioCobroProps> = ({ documentoId, saldo, onClose, onRegistrado, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const [fecha, setFecha] = useState(hoyBogota());
    const [valor, setValor] = useState(saldo);
    const [medio, setMedio] = useState<ApiInvoicePaymentMethod>('transferencia');
    const [referencia, setReferencia] = useState('');
    const [observacion, setObservacion] = useState('');

    const valorNumero = Number(valor);
    const valorValido = valor.trim() !== '' && valorNumero > 0 && valorNumero <= Number(saldo);

    const registrar = async () => {
        try {
            await sendRequest(`/facturacion/documentos/${documentoId}/cobros`, {
                method: 'POST',
                body: {
                    fecha,
                    valor,
                    medio,
                    referencia: referencia.trim() === '' ? null : referencia.trim(),
                    observacion: observacion.trim() === '' ? null : observacion.trim(),
                },
            });
            onRegistrado('Cobro registrado. El saldo y el asiento se actualizaron.');
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar cobro</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">Saldo pendiente: {formatMoney(saldo)}</p>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="cobro-fecha">Fecha</Label>
                    <DatePicker id="cobro-fecha" value={fecha} onChange={setFecha} required />
                </div>
                <div>
                    <Label htmlFor="cobro-valor">Valor</Label>
                    <Input
                        id="cobro-valor"
                        type="number"
                        min="0.01"
                        step={0.01}
                        max={saldo}
                        value={valor}
                        error={valor !== '' && !valorValido}
                        hint={valor !== '' && !valorValido ? 'Debe ser mayor que cero y no superar el saldo.' : undefined}
                        onChange={(event) => setValor(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="cobro-medio">Medio de pago</Label>
                    <Select id="cobro-medio" options={PAYMENT_METHOD_OPTIONS} defaultValue={medio} onChange={(valorSeleccionado) => setMedio(valorSeleccionado as ApiInvoicePaymentMethod)} />
                </div>
                <div>
                    <Label htmlFor="cobro-referencia">Referencia</Label>
                    <Input id="cobro-referencia" value={referencia} maxLength={60} onChange={(event) => setReferencia(event.target.value)} />
                </div>
                <div className="sm:col-span-2">
                    <Label htmlFor="cobro-observacion">Observación</Label>
                    <Input id="cobro-observacion" value={observacion} maxLength={500} onChange={(event) => setObservacion(event.target.value)} />
                </div>
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !valorValido || fecha === ''} onClick={() => void registrar()}>
                    {isLoading ? 'Registrando…' : 'Registrar cobro'}
                </Button>
            </div>
        </div>
    );
};
