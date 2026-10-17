import type { FC } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import type { EmailSettingsAccountStepProps } from '../props/email-settings-step-props';

/** Paso 2: remitente y, si el servidor lo pide, usuario y clave. La clave guardada no se muestra; escribir una nueva la reemplaza. */
export const EmailSettingsAccountStep: FC<EmailSettingsAccountStepProps> = ({ draft, errores, claveGuardada, onChange }) => (
    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <Label htmlFor="remitente">Correo remitente</Label>
            <Input
                id="remitente"
                name="remitente"
                type="email"
                required
                value={draft.remitente}
                error={Boolean(errores.remitente)}
                hint={errores.remitente}
                onChange={(event) => onChange({ remitente: event.target.value })}
            />
        </div>
        <div>
            <Label htmlFor="nombre-remitente">Nombre del remitente</Label>
            <Input
                id="nombre-remitente"
                name="nombre-remitente"
                required
                value={draft.nombreRemitente}
                error={Boolean(errores.nombreRemitente)}
                hint={errores.nombreRemitente}
                onChange={(event) => onChange({ nombreRemitente: event.target.value })}
            />
        </div>

        <div>
            <Label htmlFor="usuario">Usuario del servidor</Label>
            <Input id="usuario" name="usuario" autoComplete="off" value={draft.usuario} onChange={(event) => onChange({ usuario: event.target.value })} />
        </div>
        <div>
            <Label htmlFor="clave">Clave</Label>
            <Input
                id="clave"
                name="clave"
                type="password"
                autoComplete="current-password"
                placeholder={claveGuardada ? 'Guardada: escriba una nueva para cambiarla' : 'Sin configurar'}
                value={draft.clave}
                onChange={(event) => onChange({ clave: event.target.value })}
            />
        </div>
    </div>
);
