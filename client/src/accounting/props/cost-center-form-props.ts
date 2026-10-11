import type { ApiCostCenter } from '../types/api-cost-center';

export type CostCenterFormProps = {
    costCenterId?: number;
    initialData?: ApiCostCenter;
    onSaved: (centro: ApiCostCenter) => void;
};
