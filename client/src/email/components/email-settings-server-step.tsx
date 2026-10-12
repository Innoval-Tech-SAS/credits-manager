import type { FC } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { CIFRADO_OPTIONS, DRIVER_OPTIONS } from '../constants/email-options';
import type { EmailSettingsServerStepProps } from '../props/email-settings-step-props';
import type { EmailSettingsDraft } from '../types/email-settings-draft';

/** Paso 1: proveedor, servidor, puerto y cifrado. */
export const EmailSettingsServerStep: FC<EmailSettingsServerStepProps> = ({ draft, errores, onChange }) => (
    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div className="md:col-span-2">
            <Label htmlFor="driver">Proveedor</Label>
            <Select id="driver" options={DRIVER_OPTIONS} defaultValue={draft.driver} onChange={(valor) => onChange({ driver: valor as EmailSettingsDraft['driver'] })} />
            {draft.driver === 'registro' && (
                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    No necesita servidor: los correos se escriben en el log de la aplicación. Úselo solo para pruebas.
                </p>
            )}
        </div>

        {draft.driver === 'smtp' && (
            <>
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
            </>
        )}
    </div>
);
