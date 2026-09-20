import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiParametrization } from '../types/api-parametrization';

export type ParametrizationFormProps = {
    tiposGasto: ApiExpenseType[];
    cuentas: ApiAccount[];
    tiposComprobante: ApiVoucherType[];
    onCreated: (parametrizacion: ApiParametrization) => void;
};
