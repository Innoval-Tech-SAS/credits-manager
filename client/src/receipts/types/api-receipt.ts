import type { ApiThirdParty } from '../../third-parties/types/api-third-party';

/** Raw shape returned by `GET /recibos`: el recibo con el pago, el cliente y el crédito de los que sale. */
export type ApiReceipt = {
    id: number;
    numero: string;
    estado: 'emitido' | 'anulado';
    anulado_en: string | null;
    motivo_anulacion: string | null;
    anulador: { nombre: string } | null;
    pago: {
        id: number;
        fecha_pago: string;
        valor_pago: string;
        forma_pago: string;
        cliente: { tercero: ApiThirdParty };
        credito: { id: number; codigo: string | null } | null;
    };
};
