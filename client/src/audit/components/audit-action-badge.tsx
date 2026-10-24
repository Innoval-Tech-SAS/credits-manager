import type { FC } from 'react';

import { AUDIT_ACTION_BADGE_CLASSES, AUDIT_ACTION_LABELS } from '../constants/audit-actions';
import type { AuditActionBadgeProps } from '../props/audit-action-badge-props';

export const AuditActionBadge: FC<AuditActionBadgeProps> = ({ action }) => (
    <span
        className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${
            AUDIT_ACTION_BADGE_CLASSES[action] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
        }`}
    >
        {AUDIT_ACTION_LABELS[action] ?? action}
    </span>
);
