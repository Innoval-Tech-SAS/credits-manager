/** Raw shape of `vista_previa` from POST /correo/vista-previa and POST /clientes/{id}/recordatorios-correo/vista-previa. */
export type ApiEmailPreview = {
    asunto: string;
    cuerpo: string;
    destinatario?: string | null;
};

/** Raw shape returned by POST /correo/configuracion/prueba. */
export type ApiEmailTestResult = {
    enviado: boolean;
    mensaje: string;
};
