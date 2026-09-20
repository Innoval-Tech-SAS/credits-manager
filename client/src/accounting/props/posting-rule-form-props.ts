import type { ApiAccount } from '../types/api-account';
import type { ApiPostingRule } from '../types/api-posting-rule';
import type { ApiVoucherType } from '../types/api-voucher-type';

export type PostingRuleFormProps = {
    evento: string;
    reglaActual?: ApiPostingRule;
    cuentas: ApiAccount[];
    tipos: ApiVoucherType[];
    onSaved: (regla: ApiPostingRule) => void;
};
