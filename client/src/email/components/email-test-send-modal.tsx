import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import type { EmailTestSendModalProps } from '../props/email-test-send-modal-props';
import type { ApiEmailTestResult } from '../types/api-email-preview';

/** Envío de prueba con la configuración guardada. Muestra el resultado sin credenciales. */
export const EmailTestSendModal: FC<EmailTestSendModalProps> = ({ onClose }) => {
    const { sendRequest } = useSendRequest();

    const [destinatario, setDestinatario] = useState('');
    const [resultado, setResultado] = useState<ApiEmailTestResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isTesting, setIsTesting] = useState(false);

    const handleTest = async () => {
        setError(null);
        setResultado(null);
        setIsTesting(true);

        try {
            setResultado(
                await sendRequest<ApiEmailTestResult>('/correo/configuracion/prueba', {
                    method: 'POST',
                    body: { destinatario },
                }),
            );
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsTesting(false);
        }
    };

    return (
        <Modal isOpen onClose={onClose} size="md">
            <div className="flex flex-col gap-5 p-6">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Probar la configuración</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Envía un correo de prueba con la configuración guardada. No muestra credenciales.
                    </p>
                </div>

                <div>
                    <Label htmlFor="destinatario-prueba">Destinatario</Label>
                    <Input id="destinatario-prueba" name="destinatario-prueba" type="email" value={destinatario} onChange={(event) => setDestinatario(event.target.value)} />
                </div>

                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}
                {resultado && (
                    <div
                        className={
                            resultado.enviado
                                ? 'rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400'
                                : 'rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400'
                        }
                    >
                        {resultado.mensaje}
                    </div>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cerrar
                    </Button>
                    <Button type="button" disabled={isTesting || destinatario === ''} onClick={() => void handleTest()}>
                        {isTesting ? 'Enviando...' : 'Enviar prueba'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};
