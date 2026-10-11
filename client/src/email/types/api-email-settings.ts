/** Raw shape returned by GET and PUT /correo/configuracion/{tipo}. Nunca trae la clave: solo `clave_estado`. */
export type ApiEmailSettings = {
    estado: 'configurada' | 'sin_configurar';
    clave_estado: 'configurada' | 'sin_configurar';
    driver: 'smtp' | 'registro';
    remitente: string | null;
    nombre_remitente: string | null;
    host: string | null;
    puerto: number | null;
    cifrado: 'tls' | 'ssl' | 'ninguno';
    usuario: string | null;
};
