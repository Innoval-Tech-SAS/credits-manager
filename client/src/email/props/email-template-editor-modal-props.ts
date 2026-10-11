import type { ApiPlantillaCorreo } from '../types/api-plantilla-correo';
import type { EmailType } from '../types/email-type';

export type EmailTemplateEditorModalProps = {
    tipo: EmailType;
    /** `null` crea una plantilla nueva; con una plantilla, la edita. */
    plantilla: ApiPlantillaCorreo | null;
    onClose: () => void;
    onSaved: () => void;
};
