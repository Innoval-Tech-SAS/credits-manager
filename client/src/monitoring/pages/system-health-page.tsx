import type { FC } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DashboardSkeletonBody } from '@ui/skeleton/components/dashboard-skeleton-body';
import { useResource } from '../../shared/hooks/use-resource';
import { BackupsStatusCard } from '../components/backups-status-card';
import { FailedJobsCard } from '../components/failed-jobs-card';
import { LiveIndicator } from '../components/live-indicator';
import { QueuesCard } from '../components/queues-card';
import { ScheduledTasksStatusCard } from '../components/scheduled-tasks-status-card';
import { ServerMetricsCards } from '../components/server-metrics-cards';
import { ServicesStatusCard } from '../components/services-status-card';
import { UsageChartCard } from '../components/usage-chart-card';
import { useSystemHealth } from '../hooks/use-system-health';
import type { ApiBackupsStatus } from '../types/api-backups-status';

/** Solo superadmin: lee infraestructura (servidor, servicios, colas, respaldos), nunca datos de negocio. */
export const SystemHealthPage: FC = () => {
    const { health, history, liveStatus, error } = useSystemHealth();
    const { data: backups, error: backupsError } = useResource<ApiBackupsStatus>('/monitoreo/respaldos');

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Salud del sistema" />

            <div className="mb-6">
                <LiveIndicator status={liveStatus} generatedAt={health?.generado_en ?? null} />
            </div>

            {error && !health && (
                <div className="mb-6">
                    <Alert variant="danger" title="No se pudo cargar el estado" message={error} />
                </div>
            )}

            {!health && !error && <DashboardSkeletonBody />}

            {health && (
                <>
                    <ServerMetricsCards servidor={health.servidor} />

                    <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                        <div className="xl:col-span-7">
                            <UsageChartCard history={history} />
                        </div>
                        <div className="xl:col-span-5">
                            <ServicesStatusCard servicios={health.servicios} />
                        </div>
                    </div>

                    <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                        <div className="xl:col-span-5">
                            <QueuesCard colas={health.colas} auditoria={health.auditoria} />
                        </div>
                        <div className="xl:col-span-7">
                            <BackupsStatusCard status={backups} error={backupsError} />
                        </div>
                    </div>

                    <div className="mb-6">
                        <ScheduledTasksStatusCard tareasProgramadas={health.tareas_programadas} />
                    </div>
                </>
            )}

            <FailedJobsCard />
        </div>
    );
};
