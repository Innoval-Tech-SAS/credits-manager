import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import type { ApiPayment } from './api-payment';

/** Raw shape returned by `GET /pagos` — un `ApiPayment` con las relaciones que solo necesita el listado global. */
export type ApiPaymentListItem = ApiPayment & {
    cliente: {
        id: number;
        tercero: ApiThirdParty;
    };
};
