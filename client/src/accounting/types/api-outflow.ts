export type ApiOutflowPaymentMethod = 'efectivo' | 'transferencia' | 'consignacion' | 'cheque';

/** Raw shape returned by `POST /egresos`. */
export type ApiOutflow = {
    id: number;
    numero: string;
    fecha: string;
    valor: string;
    medio_pago: ApiOutflowPaymentMethod;
    comprobante_id: number | null;
};
