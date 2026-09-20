import type { ApiVoucherType } from '../types/api-voucher-type';

export type VoucherTypeFormProps = {
    onCreated: (tipo: ApiVoucherType) => void;
};
