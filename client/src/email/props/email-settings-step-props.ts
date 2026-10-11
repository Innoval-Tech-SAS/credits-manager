import type { EmailSettingsDraft } from '../types/email-settings-draft';
import type { EmailSettingsErrors } from '../utils/validate-email-settings-step';

export type EmailSettingsServerStepProps = {
    draft: EmailSettingsDraft;
    errores: EmailSettingsErrors;
    onChange: (cambios: Partial<EmailSettingsDraft>) => void;
};

export type EmailSettingsAccountStepProps = {
    draft: EmailSettingsDraft;
    errores: EmailSettingsErrors;
    claveGuardada: boolean;
    onChange: (cambios: Partial<EmailSettingsDraft>) => void;
};
