import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { GOAL_TYPE_LABELS } from '../constants/goal-status';
import type { GoalProgressCardProps } from '../props/goal-progress-card-props';
import { anchoDeBarra } from '../utils/periodo-meta';
import { GoalStatusBadge } from './goal-status-badge';

export const GoalProgressCard: FC<GoalProgressCardProps> = ({ tipo, meta }) => {
    const { ejecutado, porcentaje, brecha, estado, objetivo } = meta.empresa;

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">{GOAL_TYPE_LABELS[tipo]}</h3>
                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                        Objetivo {formatMoney(objetivo)}
                        {meta.cerrado ? ' · Período cerrado' : ''}
                    </p>
                </div>
                <GoalStatusBadge estado={estado} />
            </div>

            <div className="mt-5 flex items-end justify-between gap-3">
                <div>
                    <span className="text-sm text-gray-500 dark:text-gray-400">Ejecutado</span>
                    <h4 className="mt-1 text-title-sm font-bold text-gray-800 dark:text-white/90">{formatMoney(ejecutado)}</h4>
                </div>
                <span className="text-2xl font-semibold text-gray-800 dark:text-white/90">{porcentaje}%</span>
            </div>

            <div className="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuenow={parseFloat(porcentaje)} aria-valuemin={0} aria-valuemax={100}>
                <div className="h-full rounded-full bg-brand-500 dark:bg-brand-400" style={{ width: anchoDeBarra(porcentaje) }} />
            </div>

            <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">Brecha pendiente: {formatMoney(brecha)}</p>
        </div>
    );
};
