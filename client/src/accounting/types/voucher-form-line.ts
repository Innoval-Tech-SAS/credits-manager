import type { ApiThirdParty } from '../../third-parties/types/api-third-party';

export type VoucherFormLine = {
    key: number;
    cuenta_id: string;
    centro_costo_id: string;
    tercero: ApiThirdParty | null;
    documento_cruce: string;
    fecha_vencimiento: string;
    descripcion: string;
    debito: string;
    credito: string;
};
