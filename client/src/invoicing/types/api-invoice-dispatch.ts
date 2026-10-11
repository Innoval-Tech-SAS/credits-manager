/** Raw shape of each item in `envios` from GET /facturacion/documentos/{id}/envios. */
export type ApiInvoiceDispatch = {
    id: number;
    destinatario: string;
    asunto: string;
    estado: 'en_cola' | 'enviado' | 'fallido';
    detalle_error: string | null;
    enviado_en: string | null;
    creado_en: string | null;
};

/** Raw shape of GET /facturacion/documentos/{id}/envios. */
export type ApiInvoiceDispatchHistory = {
    envios: ApiInvoiceDispatch[];
};
