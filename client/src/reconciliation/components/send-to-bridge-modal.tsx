import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { ApiMarcaConciliacion } from '../types/api-match-conciliacion';
import type { SendToBridgeModalProps } from '../props/send-to-bridge-modal-props';

/** Un movimiento del extracto sin contraparte identificada se lleva a la cuenta puente con un comprobante nuevo. */
export const SendToBridgeModal: FC<SendToBridgeModalProps> = ({ isOpen, movimiento, onClose, onSent, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
        {isOpen && movimiento && <FormularioPuente movimiento={movimiento} onClose={onClose} onSent={onSent} onError={onError} />}
    </Modal>
);

type FormularioPuenteProps = Omit<SendToBridgeModalProps, 'isOpen' | 'movimiento'> & { movimiento: NonNullable<SendToBridgeModalProps['movimiento']> };

const FormularioPuente: FC<FormularioPuenteProps> = ({ movimiento, onClose, onSent, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const { data: tiposData, isLoading: isLoadingTipos } = useResource<PaginatedResponse<ApiVoucherType>>('/contabilidad/tipos-comprobante?per_page=100');
    const tipos = tiposData?.data ?? [];

    const [tipoComprobanteId, setTipoComprobanteId] = useState('');
    const [observacion, setObservacion] = useState('');

    const formularioValido = tipoComprobanteId !== '';

    const enviar = async () => {
        try {
            await sendRequest<{ marca: ApiMarcaConciliacion }>(`/conciliacion/movimientos/${movimiento.id}/enviar-a-puente`, {
                method: 'POST',
                body: { tipo_comprobante_id: tipoComprobanteId, observacion: observacion.trim() || null },
            });

            onSent(`Movimiento #${movimiento.id} enviado a la cuenta puente.`);
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Enviar a cuenta puente</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {movimiento.descripcion} — {formatMoney(movimiento.valor)}
                </p>
            </div>

            {isLoadingTipos ? (
                <div className="flex justify-center py-8">
                    <Spinner size="lg" />
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div>
                        <Label htmlFor="puente-tipo-comprobante">Tipo de comprobante</Label>
                        <Select
                            options={tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }))}
                            placeholder="Selecciona el tipo"
                            onChange={setTipoComprobanteId}
                        />
                    </div>
                    <div>
                        <Label htmlFor="puente-observacion">Observación (opcional)</Label>
                        <Input id="puente-observacion" value={observacion} maxLength={255} onChange={(event) => setObservacion(event.target.value)} />
                    </div>
                </div>
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || isLoadingTipos || !formularioValido} onClick={() => void enviar()}>
                    {isLoading ? 'Enviando…' : 'Enviar a puente'}
                </Button>
            </div>
        </div>
    );
};
