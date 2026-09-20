import type { ApiClient } from '../../clients/types/api-client';
import type { ApiInstallment } from './api-installment';
import type { CreditStatus } from './credit-status';
import type { CreditType } from './credit-type';
import type { PaymentType } from './payment-type';

/** Raw shape returned by `GET /creditos` (rows), `GET /creditos/{id}` and `POST /creditos`. */
export type ApiCredit = {
    id: number;
    cliente_id: number;
    cliente?: ApiClient;
    tipo_credito: CreditType;
    credito_origen_id: number | null;
    credito_origen?: ApiCredit | null;
    valor_prestamo: string;
    tasa_interes: string;
    plazo: number;
    tipo_pago: PaymentType;
    valor_cuota: string;
    valor_total: string;
    fecha_desembolso: string;
    desembolsado_en: string | null;
    estado: CreditStatus;
    creado_en: string | null;
    /** Solo presente en el listado (`GET /creditos`). */
    cuotas_vencidas?: number;
    /** Solo presente en el listado (`GET /creditos`). */
    pagos_count?: number;
    /** Solo presente en el detalle (`GET /creditos/{id}`). */
    cuotas?: ApiInstallment[];
};
