import type { EmailType } from './email-type';

/** Raw shape returned by GET/POST/PUT /correo/plantillas. */
export type ApiPlantillaCorreo = {
    id: number;
    tipo: EmailType;
    nombre: string;
    asunto: string;
    cuerpo: string;
    es_predeterminada: boolean;
    actualizado_por: number | null;
    creado_en: string | null;
    ultima_actualizacion: string | null;
};
