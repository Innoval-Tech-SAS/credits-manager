import type { ApiPayment } from '../../payments/types/api-payment';
import type { ApiCredit } from './api-credit';
import type { ApiRefinancingLink } from './api-refinancing-link';
import type { ApiResumenCuotas } from './api-resumen-cuotas';

/** Raw shape returned by `GET /creditos/{id}/estado-cuenta`. */
export type ApiAccountStatement = {
    credito: ApiCredit;
    /** Fecha (YYYY-MM-DD, hora de Bogotá) hasta la que el estado refleja los movimientos. */
    fecha_corte: string;
    /** Decimal string (ej. "12345.66"), no number — viene de `App\Core\Dinero\Dinero`. */
    saldo: string;
    refinanciacion: ApiRefinancingLink | null;
    pagos: ApiPayment[];
    resumen_cuotas: ApiResumenCuotas;
};
