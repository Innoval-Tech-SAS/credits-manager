import type { ApiCostCenter } from '../types/api-cost-center';

export type CostCenterFormProps = {
    onCreated: (centro: ApiCostCenter) => void;
};
