import type { ApiInvoiceConceptType } from '../types/api-invoice';

export type InvoiceLineDraft = {
    concepto: string;
    descripcion: string;
    /** Pesos como texto, tal como se digita. Se convierte en el servidor. */
    valor: string;
    /** Soporte del cálculo, solo para nota débito por intereses de mora. */
    tasa_mora?: string;
    dias_mora?: string;
    base_mora?: string;
    /** Solo en línea de factura: traza la línea a su cuota del plan de pagos. Vacío en líneas agregadas a mano. */
    cuota_id?: number | null;
    tipo_concepto?: ApiInvoiceConceptType | '';
};

export type InvoiceLinesFormProps = {
    lineas: InvoiceLineDraft[];
    onChange: (lineas: InvoiceLineDraft[]) => void;
    disabled?: boolean;
    /** Nota débito con motivo «intereses»: pide tasa, días y base del cálculo en cada línea. */
    mostrarSoporteMora?: boolean;
    /** Factura (crear/editar borrador): cada línea exige su tipo de concepto; las de una cuota lo traen fijo. */
    requiereTipoConcepto?: boolean;
};
