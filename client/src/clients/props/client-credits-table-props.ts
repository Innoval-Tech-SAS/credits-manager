import type { ApiCredit } from '../../credits/types/api-credit';

export type ClientCreditsTableProps = {
    creditos: ApiCredit[];
    onView: (creditoId: number) => void;
};
