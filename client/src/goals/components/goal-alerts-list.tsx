import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { GOAL_TYPE_LABELS } from '../constants/goal-status';
import type { GoalAlertsListProps } from '../props/goal-alerts-list-props';
import { GoalStatusBadge } from './goal-status-badge';

const FORMATO_FECHA: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };

export const GoalAlertsList: FC<GoalAlertsListProps> = ({ alertas, emptyMessage, onMarkSeen, markingId = null }) => {
    if (alertas.length === 0) {
        return <p className="text-sm text-gray-500 dark:text-gray-400">{emptyMessage}</p>;
    }

    return (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {alertas.map((alerta) => (
                <li key={alerta.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                            {GOAL_TYPE_LABELS[alerta.tipo]} · {alerta.periodo} · {alerta.asesor ?? 'Empresa'}
                        </p>
                        <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                            {new Date(alerta.generada_en).toLocaleString('es-CO', FORMATO_FECHA)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <GoalStatusBadge estado={alerta.estado} />
                        {onMarkSeen && alerta.vista_en === null && (
                            <Button size="sm" variant="outline" disabled={markingId === alerta.id} onClick={() => onMarkSeen(alerta.id)}>
                                Marcar como vista
                            </Button>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
};
