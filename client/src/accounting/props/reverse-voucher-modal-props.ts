export type ReverseVoucherModalProps = {
    isOpen: boolean;
    onClose: () => void;
    voucherId: number;
    onReversed: (nuevoComprobanteId: number) => void;
};
