import type { PaymentMethod } from './payment-method';

/** Raw shape returned by `GET/POST /creditos/{id}/pagos`. */
export type ApiPayment = {
    id: number;
    credito_id: number;
    cliente_id: number;
    valor_pago: string;
    fecha_pago: string;
    forma_pago: PaymentMethod;
    observacion: string | null;
    /** Desglose del pago. Nulo solo en pagos anteriores a la columna; decimal string. */
    valor_capital: string | null;
    valor_interes: string | null;
    valor_otros_gastos: string | null;
    /** Recibo de caja del pago; nulo solo en pagos anteriores a su emisión que aún no se respaldaron. */
    recibo: { id: number; numero: string; estado: string } | null;
};
