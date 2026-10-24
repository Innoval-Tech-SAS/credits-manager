import type { FC } from 'react';

import { IMPORT_STATUS_BADGE_CLASSES, IMPORT_STATUS_LABELS } from '../constants/import-status';
import type { ImportStatusBadgeProps } from '../props/import-status-badge-props';

export const ImportStatusBadge: FC<ImportStatusBadgeProps> = ({ status }) => (
    <span className={`inline-flex w-fit self-start rounded-full px-2.5 py-0.5 text-theme-xs font-medium ${IMPORT_STATUS_BADGE_CLASSES[status]}`}>
        {IMPORT_STATUS_LABELS[status]}
    </span>
);
