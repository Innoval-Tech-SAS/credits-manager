import type { ApiEmailSettings } from '../types/api-email-settings';

export type EmailSettingsFormProps = {
    settings: ApiEmailSettings;
    onSaved: () => void;
};
