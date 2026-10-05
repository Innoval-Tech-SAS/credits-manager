import type { ApiEmailSettings } from '../types/api-email-settings';

export type EmailVariablePickerProps = {
    variables: ApiEmailSettings['variables'];
    onPick: (clave: string) => void;
};
