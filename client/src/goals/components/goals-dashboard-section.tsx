import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { Button } from '@ui/button/components/button';
import { SkeletonStatus } from '@ui/skeleton/components/skeleton-status';
import type { ApiGoalAlert } from '../types/api-goal-alert';
import type { ApiGoalsPeriod } from '../types/api-goals-period';
import { GoalAlertsList } from './goal-alerts-list';
import { GoalProgressCard } from './goal-progress-card';
import { GoalProgressCardSkeleton } from './goal-progress-card-skeleton';
import { periodoActual } from '../utils/periodo-meta';

const ORDEN_TIPOS = ['colocacion', 'recaudo'] as const;

/** Sin acceso a metas (un asesor sin la meta consolidada) la sección no se muestra. */
export const GoalsDashboardSection: FC = () => {
    const navigate = useNavigate();
    const periodo = periodoActual();

    const { data: resumen, error } = useResource<ApiGoalsPeriod>(`/metas?periodo=${periodo}`);
    const { data: alertas } = useResource<{ alertas: ApiGoalAlert[] }>('/metas/alertas');

    if (error) {
        return null;
    }

    return (
        <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Metas del mes</h2>
                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">Avance de colocación y recaudo frente al objetivo.</p>
                </div>
                <Button size="sm" variant="outline" onClick={() => navigate('/metas')}>
                    Ver metas
                </Button>
            </div>

            {!resumen ? (
                <SkeletonStatus className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                    <GoalProgressCardSkeleton />
                    <GoalProgressCardSkeleton />
                </SkeletonStatus>
            ) : resumen.metas.length === 0 ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">No hay metas configuradas para este mes.</p>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                    {ORDEN_TIPOS.map((tipo) => {
                        const meta = resumen.metas.find((candidata) => candidata.tipo === tipo);

                        return meta ? <GoalProgressCard key={tipo} tipo={tipo} meta={meta} /> : null;
                    })}
                </div>
            )}

            <div className="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <h3 className="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">Alertas</h3>
                <GoalAlertsList alertas={(alertas?.alertas ?? []).slice(0, 5)} emptyMessage="Sin alertas de metas por ahora." />
            </div>
        </section>
    );
};
