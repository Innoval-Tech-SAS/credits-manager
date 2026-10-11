import type { ApiInvoice } from '../types/api-invoice';

export type InvoiceNoteFormProps = {
    isOpen: boolean;
    /** Documento emitido sobre el que se emite la nota. */
    documento: ApiInvoice;
    onClose: () => void;
    onEmitida: () => void;
    onError: (mensaje: string) => void;
};
