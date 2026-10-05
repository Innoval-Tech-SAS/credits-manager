import type { FC } from 'react';

import { Modal } from '@ui/modal/components/modal';
import { HtmlPreview } from './html-preview';
import type { EmailPreviewModalProps } from '../props/email-preview-modal-props';

/** Vista previa del correo con datos de ejemplo. No envía nada ni guarda la plantilla. */
export const EmailPreviewModal: FC<EmailPreviewModalProps> = ({ preview, onClose }) => (
    <Modal isOpen onClose={onClose} size="lg">
        <div className="flex flex-col gap-4 p-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Vista previa del correo</h2>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Datos de ejemplo. El correo real usa los datos de cada cliente.</p>
            </div>

            <div>
                <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Asunto</p>
                <p className="text-sm text-gray-800 dark:text-white/90">{preview.asunto}</p>
            </div>

            <HtmlPreview html={preview.cuerpo} title="Vista previa del correo" />

            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </Modal>
);
