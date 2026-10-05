import type { ApiGoalType } from '../types/api-goals-period';

export type GoalRecalcFormProps = {
    periodo: string;
    tipo: ApiGoalType;
    onRecalculated: () => void;
};
