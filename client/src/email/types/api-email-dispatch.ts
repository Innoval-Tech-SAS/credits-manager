/** Raw shape of each item in `envios` from GET /clientes/{id}/recordatorios-correo. */
export type ApiEmailDispatch = {
    id: number;
    cuota_id: number | null;
    destinatario: string;
    asunto: string;
    estado: 'en_cola' | 'enviado' | 'fallido';
    detalle_error: string | null;
    enviado_en: string | null;
    creado_en: string | null;
};

/** Raw shape of each item in `cuotas_cobrables` from GET /clientes/{id}/recordatorios-correo. `valor` es cadena decimal. */
export type ApiCollectibleInstallment = {
    id: number;
    numero_cuota: number;
    credito_codigo: string;
    valor: string;
    fecha_vencimiento: string;
    estado: 'pendiente' | 'vencida';
};

/** Raw shape of GET /clientes/{id}/recordatorios-correo. */
export type ApiReminderHistory = {
    envios: ApiEmailDispatch[];
    cuotas_cobrables: ApiCollectibleInstallment[];
};
