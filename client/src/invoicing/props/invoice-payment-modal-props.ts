export type InvoicePaymentModalProps = {
    isOpen: boolean;
    documentoId: number;
    /** Saldo pendiente en pesos, decimal string. Un cobro no puede superarlo. */
    saldo: string;
    onClose: () => void;
    onRegistrado: (mensaje: string) => void;
    onError: (mensaje: string) => void;
};
