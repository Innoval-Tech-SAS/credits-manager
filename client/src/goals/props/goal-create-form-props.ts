import type { ApiGoalType } from '../types/api-goals-period';

export type GoalCreateFormProps = {
    periodo: string;
    tiposDisponibles: ApiGoalType[];
    onCreated: () => void;
};
