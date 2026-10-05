import type { ApiGoalAlert } from '../types/api-goal-alert';

export type GoalAlertsListProps = {
    alertas: ApiGoalAlert[];
    emptyMessage: string;
    onMarkSeen?: (alertaId: number) => void;
    markingId?: number | null;
};
