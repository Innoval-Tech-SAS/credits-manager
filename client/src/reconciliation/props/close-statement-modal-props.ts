export type CloseStatementModalProps = {
    isOpen: boolean;
    extracto: { id: number; saldo_actual: string } | null;
    pendientes: number;
    onClose: () => void;
    onClosed: (message: string) => void;
    onError: (message: string) => void;
};
