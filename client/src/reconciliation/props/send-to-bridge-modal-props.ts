import type { ApiExtractoMovimiento } from '../types/api-match-conciliacion';

export type SendToBridgeModalProps = {
    isOpen: boolean;
    movimiento: ApiExtractoMovimiento | null;
    onClose: () => void;
    onSent: (mensaje: string) => void;
    onError: (mensaje: string) => void;
};
