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
};
