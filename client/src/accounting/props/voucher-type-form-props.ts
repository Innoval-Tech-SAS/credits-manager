import type { ApiVoucherType } from '../types/api-voucher-type';

export type VoucherTypeFormProps = {
    voucherTypeId?: number;
    initialData?: ApiVoucherType;
    onSaved: (tipo: ApiVoucherType) => void;
};
