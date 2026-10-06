export type ReverseVoucherModalProps = {
    isOpen: boolean;
    onClose: () => void;
    voucherId: number;
    onReversed: (nuevoComprobanteId: number) => void;
    /** Borrador o revisado: se anula sin reverso, porque nunca afectó los reportes. */
    sinEfecto?: boolean;
};
