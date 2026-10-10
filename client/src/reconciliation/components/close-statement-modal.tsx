import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import type { CloseStatementModalProps } from '../props/close-statement-modal-props';

/** Cierre de un extracto: exige cero movimientos pendientes; el cuadre contra el libro lo valida el backend al confirmar. */
export const CloseStatementModal: FC<CloseStatementModalProps> = ({ isOpen, extracto, pendientes, onClose, onClosed, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
        {isOpen && extracto && (
            <FormularioCierre extracto={extracto} pendientes={pendientes} onClose={onClose} onClosed={onClosed} onError={onError} />
        )}
    </Modal>
);

type FormularioCierreProps = Omit<CloseStatementModalProps, 'isOpen' | 'extracto'> & { extracto: NonNullable<CloseStatementModalProps['extracto']> };

const FormularioCierre: FC<FormularioCierreProps> = ({ extracto, pendientes, onClose, onClosed, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();
    const [fallo, setFallo] = useState<string | null>(null);

    const puedeCerrar = pendientes === 0;

    const cerrar = async () => {
        setFallo(null);

        try {
            await sendRequest(`/conciliacion/extractos/${extracto.id}/cerrar`, { method: 'POST' });
            onClosed(`Extracto #${extracto.id} cerrado.`);
        } catch (caught) {
            const mensaje = caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.';
            setFallo(mensaje);
            onError(mensaje);
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 pr-10">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Cerrar extracto</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">Saldo del extracto: {formatMoney(extracto.saldo_actual)}</p>
            </div>

            {fallo ? (
                <Alert variant="danger" title="El cierre no se pudo completar" message={fallo} />
            ) : puedeCerrar ? (
                <Alert
                    variant="success"
                    title="Listo para cerrar"
                    message="No quedan movimientos pendientes. Al confirmar, se valida el saldo del extracto contra el libro."
                />
            ) : (
                <Alert
                    variant="warning"
                    title={`${pendientes} movimiento(s) pendiente(s)`}
                    message="Concilia o envía a cuenta puente todos los movimientos antes de cerrar el extracto."
                />
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || !puedeCerrar} onClick={() => void cerrar()}>
                    {isLoading ? 'Cerrando…' : 'Cerrar extracto'}
                </Button>
            </div>
        </div>
    );
};
