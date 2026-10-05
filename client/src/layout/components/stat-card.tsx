import type { FC } from 'react';

import { STAT_CARD_ACCENT_CLASSES } from '../constants/stat-card-accent-classes';
import type { StatCardProps } from '../props/stat-card-props';
import { StatCardCambioBadge } from './stat-card-cambio-badge';

export const StatCard: FC<StatCardProps> = ({ label, value, icon: Icon, accent = 'neutral', cambio }) => (
    <div className="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
        <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${STAT_CARD_ACCENT_CLASSES[accent]}`}>
            <Icon className="size-6" />
        </div>

        <div className="mt-5 min-w-0">
            <span className="text-sm text-gray-500 dark:text-gray-400">{label}</span>
            <h4 className="mt-2 text-title-sm font-bold wrap-break-word text-gray-800 dark:text-white/90">{value}</h4>
        </div>

        {cambio && (
            <div className="mt-auto flex flex-wrap items-center gap-2 pt-3">
                <StatCardCambioBadge cambio={cambio} />
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">{cambio.referencia}</p>
            </div>
        )}
    </div>
);
