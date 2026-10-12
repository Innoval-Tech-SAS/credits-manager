import type { ApiAccount } from '../types/api-account';

export type AccountFormProps = {
    cuentas: ApiAccount[];
    accountId?: number;
    initialData?: ApiAccount;
    onSaved: (cuenta: ApiAccount) => void;
};
