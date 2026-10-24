/** Raw shape returned by GET and PATCH /respaldos/externo. Nunca trae la clave secreta: solo `clave_secreta_estado`. */
export type ApiExternalBackupConfig = {
    activa: boolean;
    bucket: string | null;
    region: string | null;
    endpoint: string | null;
    uso_path_style: boolean;
    clave_acceso: string | null;
    clave_secreta_estado: 'configurada' | 'sin_configurar';
};
