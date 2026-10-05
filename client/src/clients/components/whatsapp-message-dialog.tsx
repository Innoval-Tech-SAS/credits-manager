import type { FC } from 'react';
import { useState } from 'react';

import { Modal } from '@ui/modal/components/modal';
import { enlaceWhatsApp, plantillaMensaje } from '../utils/whatsapp-link';
import type { WhatsAppMessageDialogProps } from '../props/whatsapp-button-props';

/** Permite editar el mensaje antes de abrir WhatsApp. Se monta solo al abrirlo, así cada vez parte de la plantilla. */
export const WhatsAppMessageDialog: FC<WhatsAppMessageDialogProps> = ({ onClose, nombre, numero, empresa }) => {
    const [texto, setTexto] = useState(() => plantillaMensaje(nombre, empresa));
    const sinTexto = texto.trim() === '';

    return (
        <Modal isOpen onClose={onClose} size="md">
            <div className="flex flex-col gap-4 p-6">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Enviar WhatsApp</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Se abre WhatsApp con este mensaje para +{numero}. Lo envía usted desde su teléfono o desde WhatsApp Web.
                    </p>
                </div>

                <label htmlFor="mensaje-whatsapp" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Mensaje
                </label>
                <textarea
                    id="mensaje-whatsapp"
                    rows={4}
                    value={texto}
                    onChange={(event) => setTexto(event.target.value)}
                    className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                />

                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        Cancelar
                    </button>
                    {sinTexto ? (
                        <span className="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white opacity-50">Abrir WhatsApp</span>
                    ) : (
                        <a
                            href={enlaceWhatsApp(numero, texto)}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={onClose}
                            className="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                        >
                            Abrir WhatsApp
                        </a>
                    )}
                </div>
            </div>
        </Modal>
    );
};
