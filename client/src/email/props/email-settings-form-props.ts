import type { ApiEmailSettings } from '../types/api-email-settings';
import type { EmailType } from '../types/email-type';

export type EmailSettingsFormProps = {
    tipo: EmailType;
    settings: ApiEmailSettings;
    onSaved: () => void;
};
