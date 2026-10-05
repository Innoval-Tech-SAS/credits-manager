import type { FC } from 'react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';

import { formatVariacion } from '../utils/format-variacion';
import type { StatCardCambioBadgeProps } from '../props/stat-card-cambio-badge-props';

export const StatCardCambioBadge: FC<StatCardCambioBadgeProps> = ({ cambio }) => {
    const { porcentaje, favorable } = cambio;

    if (porcentaje === null) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">
                <Minus className="size-3.5" />
                sin base
            </span>
        );
    }

    const Flecha = porcentaje >= 0 ? ArrowUpRight : ArrowDownRight;
    const tono = favorable
        ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
        : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';

    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-theme-xs font-medium whitespace-nowrap ${tono}`}>
            <Flecha className="size-3.5" />
            {formatVariacion(porcentaje)}
        </span>
    );
};
