import type { FC } from 'react';
import { useRef } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { EmailVariablePicker } from './email-variable-picker';
import type { EmailSettingsTemplateStepProps } from '../props/email-settings-step-props';

type CampoActivo = {
    campo: 'asunto' | 'cuerpo';
    elemento: HTMLInputElement | HTMLTextAreaElement;
};

/** Paso 3: asunto, cuerpo HTML y selector de variables. La vista previa se abre desde el pie del asistente. */
export const EmailSettingsTemplateStep: FC<EmailSettingsTemplateStepProps> = ({ draft, errores, variables, onChange }) => {
    const campoActivo = useRef<CampoActivo | null>(null);

    const insertarVariable = (clave: string) => {
        const token = `{${clave}}`;
        const campo = campoActivo.current?.campo ?? 'cuerpo';
        const elemento = campoActivo.current?.elemento ?? null;
        const valorActual = campo === 'asunto' ? draft.asunto : draft.cuerpo;
        const inicio = elemento?.selectionStart ?? valorActual.length;
        const fin = elemento?.selectionEnd ?? inicio;

        onChange({ [campo]: valorActual.slice(0, inicio) + token + valorActual.slice(fin) });

        const posicion = inicio + token.length;

        requestAnimationFrame(() => {
            if (elemento?.isConnected) {
                elemento.focus();
                elemento.setSelectionRange(posicion, posicion);
            }
        });
    };

    return (
        <div className="flex min-w-0 flex-col gap-8">
            <div className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="asunto">Asunto</Label>
                    <Input
                        id="asunto"
                        name="asunto"
                        required
                        value={draft.asunto}
                        error={Boolean(errores.asunto)}
                        hint={errores.asunto}
                        onFocus={(event) => {
                            campoActivo.current = { campo: 'asunto', elemento: event.currentTarget };
                        }}
                        onChange={(event) => onChange({ asunto: event.target.value })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="cuerpo">Cuerpo del correo (HTML)</Label>
                    <textarea
                        id="cuerpo"
                        name="cuerpo"
                        rows={12}
                        required
                        value={draft.cuerpo}
                        onFocus={(event) => {
                            campoActivo.current = { campo: 'cuerpo', elemento: event.currentTarget };
                        }}
                        onChange={(event) => onChange({ cuerpo: event.target.value })}
                        aria-invalid={Boolean(errores.cuerpo)}
                        className={`w-full rounded-lg border bg-transparent px-4 py-2.5 font-mono text-sm text-gray-800 focus:outline-hidden dark:text-white/90 ${
                            errores.cuerpo
                                ? 'border-error-500 dark:border-error-800'
                                : 'border-gray-300 focus:border-brand-300 dark:border-gray-700 dark:focus:border-brand-800'
                        }`}
                    />
                    {errores.cuerpo && <p className="text-xs text-error-600 dark:text-error-500">{errores.cuerpo}</p>}
                </div>
            </div>

            <EmailVariablePicker variables={variables} onPick={insertarVariable} />
        </div>
    );
};
