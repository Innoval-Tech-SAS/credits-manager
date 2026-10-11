import type { ApiAccount } from '../types/api-account';
import type { ApiCostCenter } from '../types/api-cost-center';
import type { ApiVoucherType } from '../types/api-voucher-type';

export type VoucherFormProps = {
    tipos: ApiVoucherType[];
    cuentas: ApiAccount[];
    centrosCosto: ApiCostCenter[];
};
