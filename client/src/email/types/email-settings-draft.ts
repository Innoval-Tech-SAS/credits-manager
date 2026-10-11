/** Borrador del formulario de correo: strings tal como se escriben; se convierte al enviar. */
export type EmailSettingsDraft = {
    driver: 'smtp' | 'registro';
    host: string;
    puerto: string;
    cifrado: 'tls' | 'ssl' | 'ninguno';
    remitente: string;
    nombreRemitente: string;
    usuario: string;
    clave: string;
};
