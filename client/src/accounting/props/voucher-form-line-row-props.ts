import type { SelectOption } from '@ui/forms/types/select-option';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import type { ApiAccount } from '../types/api-account';
import type { VoucherFormLine } from '../types/voucher-form-line';

export type VoucherFormLineRowProps = {
    linea: VoucherFormLine;
    cuentas: ApiAccount[];
    cuentaOptions: SelectOption[];
    centroCostoOptions: SelectOption[];
    terceroComprobante: ApiThirdParty | null;
    puedeEliminar: boolean;
    onActualizar: (cambios: Partial<VoucherFormLine>) => void;
    onEliminar: () => void;
};
