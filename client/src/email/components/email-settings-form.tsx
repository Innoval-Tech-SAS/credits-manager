import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { ChevronLeft, ChevronRight, Save, Send } from 'lucide-react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { EMAIL_SETTINGS_LAST_STEP, EMAIL_SETTINGS_STEPS } from '../constants/email-settings-steps';
import { EmailSettingsAccountStep } from './email-settings-account-step';
import { EmailSettingsServerStep } from './email-settings-server-step';
import { EmailSettingsStepper } from './email-settings-stepper';
import { EmailTestSendModal } from './email-test-send-modal';
import type { EmailSettingsFormProps } from '../props/email-settings-form-props';
import type { ApiEmailSettings } from '../types/api-email-settings';
import type { EmailSettingsDraft } from '../types/email-settings-draft';
import type { EmailSettingsErrors } from '../utils/validate-email-settings-step';
import { validarPasoCorreo } from '../utils/validate-email-settings-step';

function borradorDesde(settings: ApiEmailSettings): EmailSettingsDraft {
    return {
        host: settings.host ?? '',
        puerto: settings.puerto === null ? '' : String(settings.puerto),
        cifrado: settings.cifrado,
        remitente: settings.remitente ?? '',
        nombreRemitente: settings.nombre_remitente ?? '',
        usuario: settings.usuario ?? '',
        clave: '',
    };
}

/** Asistente de dos pasos (servidor y cuenta). Cada paso valida antes de avanzar; la configuración se guarda solo al final. */
export const EmailSettingsForm: FC<EmailSettingsFormProps> = ({ tipo, settings, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [draft, setDraft] = useState<EmailSettingsDraft>(() => borradorDesde(settings));
    const [paso, setPaso] = useState(0);
    const [errores, setErrores] = useState<EmailSettingsErrors>({});
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [pruebaAbierta, setPruebaAbierta] = useState(false);

    const actualizar = (cambios: Partial<EmailSettingsDraft>) => {
        setDraft((anterior) => ({ ...anterior, ...cambios }));
        setErrores({});
        setSuccess(false);
    };

    const siguiente = () => {
        const erroresPaso = validarPasoCorreo(paso, draft);
        setErrores(erroresPaso);

        if (Object.keys(erroresPaso).length === 0) {
            setPaso((actual) => Math.min(actual + 1, EMAIL_SETTINGS_LAST_STEP));
        }
    };

    const anterior = () => {
        setErrores({});
        setPaso((actual) => Math.max(actual - 1, 0));
    };

    /** Antes de guardar se revisan todos los pasos: si hay un error, se vuelve al paso que lo tiene. */
    const primerPasoConErrores = (): { paso: number; errores: EmailSettingsErrors } | null => {
        for (let indice = 0; indice <= EMAIL_SETTINGS_LAST_STEP; indice++) {
            const erroresPaso = validarPasoCorreo(indice, draft);

            if (Object.keys(erroresPaso).length > 0) {
                return { paso: indice, errores: erroresPaso };
            }
        }

        return null;
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (paso !== EMAIL_SETTINGS_LAST_STEP) {
            siguiente();

            return;
        }

        const conError = primerPasoConErrores();

        if (conError) {
            setPaso(conError.paso);
            setErrores(conError.errores);

            return;
        }

        setError(null);
        setSuccess(false);
        setIsSubmitting(true);

        try {
            await sendRequest(`/correo/configuracion/${tipo}`, {
                method: 'PUT',
                body: {
                    remitente: draft.remitente,
                    nombre_remitente: draft.nombreRemitente,
                    host: draft.host,
                    puerto: draft.puerto !== '' ? Number(draft.puerto) : null,
                    cifrado: draft.cifrado,
                    usuario: draft.usuario !== '' ? draft.usuario : null,
                    clave: draft.clave !== '' ? draft.clave : null,
                },
            });

            setDraft((anterior) => ({ ...anterior, clave: '' }));
            setSuccess(true);
            onSaved();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const esUltimo = paso === EMAIL_SETTINGS_LAST_STEP;

    return (
        <div className="flex flex-col gap-8">
            <form onSubmit={handleSubmit} className="flex flex-col gap-6" noValidate>
                <EmailSettingsStepper pasos={EMAIL_SETTINGS_STEPS} pasoActual={paso} />

                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}
                {success && (
                    <div className="rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">
                        Configuración guardada correctamente.
                    </div>
                )}
                {paso === 0 && <EmailSettingsServerStep draft={draft} errores={errores} onChange={actualizar} />}
                {paso === 1 && (
                    <EmailSettingsAccountStep draft={draft} errores={errores} claveGuardada={settings.clave_estado === 'configurada'} onChange={actualizar} />
                )}

                {esUltimo && settings.estado === 'configurada' && (
                    <div className="flex flex-col gap-3 border-t border-gray-200 pt-6 sm:flex-row dark:border-gray-800">
                        <Button type="button" variant="outline" startIcon={<Send className="size-4" />} onClick={() => setPruebaAbierta(true)}>
                            Probar
                        </Button>
                    </div>
                )}

                <div className="flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-between dark:border-gray-800">
                    <div>
                        {paso > 0 && (
                            <Button type="button" variant="outline" startIcon={<ChevronLeft className="size-4" />} onClick={anterior}>
                                Anterior
                            </Button>
                        )}
                    </div>

                    {/* Keys distintas: sin ellas React reutiliza el mismo <button> y al cambiar su type a "submit" el clic envía el formulario. */}
                    {esUltimo ? (
                        <Button key="guardar" type="submit" startIcon={<Save className="size-4" />} disabled={isSubmitting}>
                            {isSubmitting ? 'Guardando...' : 'Guardar'}
                        </Button>
                    ) : (
                        <Button key="siguiente" type="button" endIcon={<ChevronRight className="size-4" />} onClick={siguiente}>
                            Siguiente
                        </Button>
                    )}
                </div>
            </form>

            {pruebaAbierta && <EmailTestSendModal tipo={tipo} onClose={() => setPruebaAbierta(false)} />}
        </div>
    );
};
