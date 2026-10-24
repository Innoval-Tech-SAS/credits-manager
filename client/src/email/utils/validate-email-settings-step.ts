import type { EmailSettingsDraft } from '../types/email-settings-draft';

export type EmailSettingsErrors = Partial<Record<keyof EmailSettingsDraft, string>>;

/** Paso 0 servidor, 1 cuenta. Devuelve los errores del paso; vacío significa que se puede avanzar. */
export function validarPasoCorreo(paso: number, draft: EmailSettingsDraft): EmailSettingsErrors {
    const errores: EmailSettingsErrors = {};

    if (paso === 0) {
        if (draft.host.trim() === '') {
            errores.host = 'Indique el servidor.';
        }

        const puerto = Number(draft.puerto);

        if (draft.puerto === '' || !Number.isInteger(puerto) || puerto < 1 || puerto > 65535) {
            errores.puerto = 'Indique un puerto entre 1 y 65535.';
        }
    }

    if (paso === 1) {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(draft.remitente)) {
            errores.remitente = 'Indique un correo válido.';
        }

        if (draft.nombreRemitente.trim() === '') {
            errores.nombreRemitente = 'Indique el nombre del remitente.';
        }
    }

    return errores;
}
