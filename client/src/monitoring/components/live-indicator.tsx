import type { FC } from 'react';

import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { LIVE_STATUS_CLASSES, LIVE_STATUS_LABELS } from '../constants/live-status';
import type { LiveIndicatorProps } from '../props/live-indicator-props';

export const LiveIndicator: FC<LiveIndicatorProps> = ({ status, generatedAt }) => (
    <div className="flex flex-wrap items-center gap-2">
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${LIVE_STATUS_CLASSES[status]}`}>
            {status === 'en_vivo' && (
                <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-success-500 opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-success-500" />
                </span>
            )}
            {LIVE_STATUS_LABELS[status]}
        </span>

        {generatedAt && <span className="text-theme-xs text-gray-500 dark:text-gray-400">Última muestra: {formatDateTimeEs(generatedAt)}</span>}
    </div>
);
