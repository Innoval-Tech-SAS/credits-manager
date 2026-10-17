import type { FC } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { CIFRADO_OPTIONS } from '../constants/email-options';
import type { EmailSettingsServerStepProps } from '../props/email-settings-step-props';
import type { EmailSettingsDraft } from '../types/email-settings-draft';

/** Paso 1: servidor, puerto y cifrado del proveedor SMTP. */
export const EmailSettingsServerStep: FC<EmailSettingsServerStepProps> = ({ draft, errores, onChange }) => (
    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <Label htmlFor="host">Servidor (host)</Label>
            <Input
                id="host"
                name="host"
                required
                value={draft.host}
                error={Boolean(errores.host)}
                hint={errores.host}
                onChange={(event) => onChange({ host: event.target.value })}
            />
        </div>
        <div>
            <Label htmlFor="puerto">Puerto</Label>
            <Input
                id="puerto"
                name="puerto"
                type="number"
                min="1"
                max="65535"
                required
                value={draft.puerto}
                error={Boolean(errores.puerto)}
                hint={errores.puerto}
                onChange={(event) => onChange({ puerto: event.target.value })}
            />
        </div>
        <div className="md:col-span-2">
            <Label htmlFor="cifrado">Cifrado</Label>
            <Select id="cifrado" options={CIFRADO_OPTIONS} defaultValue={draft.cifrado} onChange={(valor) => onChange({ cifrado: valor as EmailSettingsDraft['cifrado'] })} />
        </div>
    </div>
);
