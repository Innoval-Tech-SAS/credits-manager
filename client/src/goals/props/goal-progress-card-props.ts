import type { ApiGoal, ApiGoalType } from '../types/api-goals-period';

export type GoalProgressCardProps = {
    tipo: ApiGoalType;
    meta: ApiGoal;
};
