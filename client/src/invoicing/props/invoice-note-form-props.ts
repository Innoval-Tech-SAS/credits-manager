import type { ApiInvoice } from '../types/api-invoice';

export type InvoiceNoteFormProps = {
    isOpen: boolean;
    /** Documento emitido sobre el que se emite la nota. */
    documento: ApiInvoice;
    /** Tipo preseleccionado al abrir, si ya se conoce la intención (ej. se llega desde "Crear nota débito"). */
    tipoInicial?: 'nota_credito' | 'nota_debito';
    onClose: () => void;
    onEmitida: () => void;
    onError: (mensaje: string) => void;
};
