import type { ApiCredit } from './api-credit';

export type BuildCreditTableColumnsOptions = {
    canCancel: boolean;
    onView: (row: ApiCredit) => void;
    onCancel: (row: ApiCredit) => void;
};
