import type { ApiAdvisor } from '../../clients/types/api-advisor';
import type { ApiGoal } from '../types/api-goals-period';

export type GoalDistributionFormProps = {
    meta: ApiGoal;
    asesores: ApiAdvisor[];
    onSaved: () => void;
};
