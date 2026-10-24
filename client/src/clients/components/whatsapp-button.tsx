import type { FC } from 'react';
import { useState } from 'react';
import { useCompany } from '../../company/hooks/use-company';
import { Button } from '@ui/button/components/button';
import { WhatsAppIcon } from '@ui/icons/components/whatsapp-icon';
import { WhatsAppMessageDialog } from './whatsapp-message-dialog';
import type { WhatsAppButtonProps } from '../props/whatsapp-button-props';
import { numeroWhatsApp } from '../utils/whatsapp-link';

/** Abre el diálogo de WhatsApp del cliente. Sin un celular colombiano válido queda deshabilitado con el motivo. */
export const WhatsAppButton: FC<WhatsAppButtonProps> = ({ nombre, celular, variant }) => {
    const { company } = useCompany();
    const [abierto, setAbierto] = useState(false);

    const numero = numeroWhatsApp(celular);
    const motivo = numero === null ? 'El cliente no tiene un celular válido registrado' : 'Enviar WhatsApp';

    return (
        <>
            {variant === 'icono' ? (
                <button
                    type="button"
                    aria-label={motivo}
                    title={motivo}
                    disabled={numero === null}
                    onClick={() => setAbierto(true)}
                    className="text-gray-500 hover:text-gray-800 disabled:opacity-40 dark:text-gray-400 dark:hover:text-white/90"
                >
                    <WhatsAppIcon className="size-5" />
                </button>
            ) : (
                <Button variant="outline" startIcon={<WhatsAppIcon className="size-4" />} disabled={numero === null} onClick={() => setAbierto(true)}>
                    WhatsApp
                </Button>
            )}

            {abierto && numero !== null && (
                <WhatsAppMessageDialog onClose={() => setAbierto(false)} nombre={nombre} numero={numero} empresa={company?.name ?? ''} />
            )}
        </>
    );
};
