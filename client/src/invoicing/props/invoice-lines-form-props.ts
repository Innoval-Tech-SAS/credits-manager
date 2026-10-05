export type InvoiceLineDraft = {
    concepto: string;
    descripcion: string;
    /** Pesos como texto, tal como se digita. Se convierte en el servidor. */
    valor: string;
};

export type InvoiceLinesFormProps = {
    lineas: InvoiceLineDraft[];
    onChange: (lineas: InvoiceLineDraft[]) => void;
    disabled?: boolean;
};
