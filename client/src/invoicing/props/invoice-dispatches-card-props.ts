export type InvoiceDispatchesCardProps = {
    documentoId: number;
    /** `facturacion.enviar`: sin él se ve el historial pero no se reintenta un envío fallido. */
    canSend: boolean;
};
