export type InvoiceMotivoModalProps = {
    isOpen: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    isSubmitting: boolean;
    onClose: () => void;
    /** Recibe el motivo ya recortado; el modal no envía nada por sí mismo. */
    onConfirm: (motivo: string) => void;
};
