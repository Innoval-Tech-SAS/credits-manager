import type { ApiInvoice } from '../types/api-invoice';

export type InvoiceNoteFormProps = {
    /** Documento emitido sobre el que se emite la nota. */
    documento: ApiInvoice;
    onEmitida: () => void;
    onError: (mensaje: string) => void;
};
