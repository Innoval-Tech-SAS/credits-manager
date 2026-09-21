import type { ApiReopenRequest } from '../types/api-reopen-request';

export type ReopenRequestsCardProps = {
    solicitudes: ApiReopenRequest[];
    /** Quien puede reabrir períodos ve los botones para autorizar o rechazar. */
    canResolve: boolean;
    /** Se llama tras autorizar o rechazar, para recargar solicitudes y períodos. */
    onResolved: () => void;
};
