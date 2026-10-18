import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiVoucherType } from '../../accounting/types/api-voucher-type';
import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiParametrization } from '../types/api-parametrization';

export type ParametrizationFormProps = {
    tiposGasto: ApiExpenseType[];
    cuentas: ApiAccount[];
    tiposComprobante: ApiVoucherType[];
    /** Presente en edición: el tipo de gasto y la vigencia quedan fijos, solo se corrige la configuración contable. */
    parametrizacionId?: number;
    initialData?: ApiParametrization;
    onSaved: (parametrizacion: ApiParametrization) => void;
};
