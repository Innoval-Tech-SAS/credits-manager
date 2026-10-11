export type InvoiceLineDraft = {
    concepto: string;
    descripcion: string;
    /** Pesos como texto, tal como se digita. Se convierte en el servidor. */
    valor: string;
    /** Soporte del cálculo, solo para nota débito por intereses de mora. */
    tasa_mora?: string;
    dias_mora?: string;
    base_mora?: string;
};

export type InvoiceLinesFormProps = {
    lineas: InvoiceLineDraft[];
    onChange: (lineas: InvoiceLineDraft[]) => void;
    disabled?: boolean;
    /** Nota débito con motivo «intereses»: pide tasa, días y base del cálculo en cada línea. */
    mostrarSoporteMora?: boolean;
};
