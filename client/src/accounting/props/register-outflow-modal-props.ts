export type OutflowDocumentToPay = {
    id: number;
    numero_documento: string;
    concepto: string;
    /** Saldo pendiente del documento en pesos, decimal string — valor por defecto a pagar, editable. */
    saldo: string;
};

export type RegisterOutflowModalProps = {
    isOpen: boolean;
    terceroId: number;
    documentos: OutflowDocumentToPay[];
    onClose: () => void;
    onRegistrado: (mensaje: string) => void;
    onError: (mensaje: string) => void;
};
