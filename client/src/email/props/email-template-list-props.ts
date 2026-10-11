import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailType } from '../types/email-type';

export type EmailTemplateListProps = {
    tipo: EmailType;
    plantillas: ApiPlantillaCorreo[];
    onChanged: () => void;
};
