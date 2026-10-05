import type { FC } from 'react';
import { useState } from 'react';
import { Mail } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { EmailReminderModal } from './email-reminder-modal';
import type { EmailReminderButtonProps } from '../props/email-reminder-button-props';

/** Abre el diálogo de cobranza por correo. Sin correo del cliente o sin cuotas por cobrar queda deshabilitado con el motivo. */
export const EmailReminderButton: FC<EmailReminderButtonProps> = ({ clienteId, correo, cuotasCobrables, onSent }) => {
    const [abierto, setAbierto] = useState(false);

    const tieneCorreo = correo !== null && correo !== '';
    const tieneCuotas = cuotasCobrables.length > 0;
    const motivo = !tieneCorreo
        ? 'El cliente no tiene un correo registrado'
        : !tieneCuotas
            ? 'No hay cuotas pendientes por cobrar'
            : 'Enviar recordatorio por correo';

    return (
        <>
            <span title={motivo} className="inline-flex">
                <Button
                    variant="outline"
                    startIcon={<Mail className="size-4" />}
                    disabled={!tieneCorreo || !tieneCuotas}
                    onClick={() => setAbierto(true)}
                >
                    Correo de cobranza
                </Button>
            </span>

            {abierto && correo && (
                <EmailReminderModal
                    clienteId={clienteId}
                    cuotasCobrables={cuotasCobrables}
                    correo={correo}
                    onClose={() => setAbierto(false)}
                    onSent={onSent}
                />
            )}
        </>
    );
};
