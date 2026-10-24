import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailType } from '../types/email-type';

export type EmailTemplateFormProps = {
    tipo: EmailType;
    templateId?: number;
    initialData?: ApiPlantillaCorreo;
};
