/** Raw shape of `vista_previa` from POST /correo/plantillas/vista-previa and POST /clientes/{id}/recordatorios-correo/vista-previa. */
export type ApiEmailPreview = {
    asunto: string;
    cuerpo: string;
    destinatario?: string | null;
};

/** Raw shape returned by POST /correo/configuracion/{tipo}/prueba. */
export type ApiEmailTestResult = {
    enviado: boolean;
    mensaje: string;
};
