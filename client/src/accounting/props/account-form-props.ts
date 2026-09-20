import type { ApiAccount } from '../types/api-account';

export type AccountFormProps = {
    cuentas: ApiAccount[];
    onCreated: (cuenta: ApiAccount) => void;
};
