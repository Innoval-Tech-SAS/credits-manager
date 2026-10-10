export type UploadBankStatementModalProps = {
    isOpen: boolean;
    cuentaBancariaId: number;
    onClose: () => void;
    onUploaded: (mensaje: string) => void;
    onError: (mensaje: string) => void;
};
