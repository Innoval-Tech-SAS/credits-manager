export type RegisterOutflowModalProps = {
    isOpen: boolean;
    terceroId: number;
    documentoTerceroId: number;
    /** Saldo pendiente del documento en pesos, decimal string. El egreso lo paga completo. */
    saldo: string;
    onClose: () => void;
    onRegistrado: (mensaje: string) => void;
    onError: (mensaje: string) => void;
};
