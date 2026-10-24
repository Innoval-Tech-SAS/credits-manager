import type { ApiEmailDispatch } from '../types/api-email-dispatch';

export type EmailDispatchesCardProps = {
    envios: ApiEmailDispatch[];
    /** Omitido, la tarjeta no muestra el botón "Reintentar" aunque haya envíos fallidos. */
    onRetry?: (envioId: number) => void;
    retryingId?: number | null;
};
