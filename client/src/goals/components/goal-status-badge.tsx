import type { FC } from 'react';

import { GOAL_STATUS_BADGE_CLASSES, GOAL_STATUS_LABELS } from '../constants/goal-status';
import type { GoalStatusBadgeProps } from '../props/goal-status-badge-props';

export const GoalStatusBadge: FC<GoalStatusBadgeProps> = ({ estado }) => (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-theme-xs font-medium ${GOAL_STATUS_BADGE_CLASSES[estado]}`}>
        {GOAL_STATUS_LABELS[estado]}
    </span>
);
