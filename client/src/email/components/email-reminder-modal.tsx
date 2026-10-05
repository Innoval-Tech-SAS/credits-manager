import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Modal } from '@ui/modal/components/modal';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { HtmlPreview } from './html-preview';
import type { EmailReminderModalProps } from '../props/email-reminder-button-props';
import type { ApiEmailPreview } from '../types/api-email-preview';

/** Elige la cuota, muestra el correo exacto que saldrá y lo encola. Lo envía el worker de correos. */
export const EmailReminderModal: FC<EmailReminderModalProps> = ({ clienteId, cuotasCobrables, correo, onClose, onSent }) => {
    const { sendRequest } = useSendRequest();

    const [cuotaId, setCuotaId] = useState('');
    const [preview, setPreview] = useState<ApiEmailPreview | null>(null);
    const [isPreviewing, setIsPreviewing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [sent, setSent] = useState(false);
    const [isSending, setIsSending] = useState(false);

    const opciones = cuotasCobrables.map((cuota) => ({
        value: String(cuota.id),
        label: `Cuota ${cuota.numero_cuota} · ${cuota.credito_codigo} · vence ${cuota.fecha_vencimiento}`,
    }));

    const handleCuotaChange = async (valor: string) => {
        setCuotaId(valor);
        setPreview(null);
        setError(null);
        setSent(false);
        setIsPreviewing(true);

        try {
            const respuesta = await sendRequest<{ vista_previa: ApiEmailPreview }>(`/clientes/${clienteId}/recordatorios-correo/vista-previa`, {
                method: 'POST',
                body: { cuota_id: Number(valor) },
            });
            setPreview(respuesta.vista_previa);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsPreviewing(false);
        }
    };

    const handleSend = async () => {
        setError(null);
        setIsSending(true);

        try {
            await sendRequest(`/clientes/${clienteId}/recordatorios-correo`, {
                method: 'POST',
                body: { cuota_id: Number(cuotaId) },
            });
            setSent(true);
            onSent();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSending(false);
        }
    };

    return (
        <Modal isOpen onClose={onClose} size="lg">
            <div className="flex flex-col gap-5 p-6">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Correo de cobranza</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Se enviará a {correo}. Revise el mensaje antes de enviarlo.</p>
                </div>

                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {sent && (
                    <div className="rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">
                        Correo en cola. El resultado aparece en el historial del cliente.
                    </div>
                )}

                <div>
                    <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Cuota a recordar</p>
                    <Select options={opciones} placeholder="Selecciona una cuota" onChange={(valor) => void handleCuotaChange(valor)} />
                </div>

                {isPreviewing && (
                    <div className="flex justify-center py-8">
                        <Spinner size="md" />
                    </div>
                )}

                {preview && !isPreviewing && (
                    <div className="flex flex-col gap-3">
                        <div>
                            <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Asunto</p>
                            <p className="text-sm text-gray-800 dark:text-white/90">{preview.asunto}</p>
                        </div>
                        <HtmlPreview html={preview.cuerpo} title="Correo de cobranza" />
                    </div>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        {sent ? 'Cerrar' : 'Cancelar'}
                    </button>
                    {!sent && (
                        <button
                            type="button"
                            disabled={!preview || isSending}
                            onClick={() => void handleSend()}
                            className="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                        >
                            {isSending ? 'Encolando...' : 'Enviar correo'}
                        </button>
                    )}
                </div>
            </div>
        </Modal>
    );
};
