import type { ApiThirdParty } from '../../third-parties/types/api-third-party';

export type ReceiptFiltersProps = {
    tercero: ApiThirdParty | null;
    onTerceroChange: (tercero: ApiThirdParty | null) => void;
    estado: string;
    onEstadoChange: (estado: string) => void;
    desde: string;
    onDesdeChange: (desde: string) => void;
    hasta: string;
    onHastaChange: (hasta: string) => void;
};
