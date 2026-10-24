import type { EmailTemplateVariable } from '../types/email-template-variable';

export type EmailVariablePickerProps = {
    variables: EmailTemplateVariable[];
    onPick: (clave: string) => void;
};
