import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useAuth } from '../../auth/hooks/use-auth';
import { usePermission } from '../../auth/hooks/use-permission';
import { periodoActual } from '../../goals/utils/periodo-meta';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DashboardSkeletonBody } from '@ui/skeleton/components/dashboard-skeleton-body';
import { SuperadminHomeSkeletonBody } from '@ui/skeleton/components/superadmin-home-skeleton-body';
import { GoalsDashboardSection } from '../../goals/components/goals-dashboard-section';
import { DashboardFilters } from '../components/dashboard-filters';
import { DashboardMetricCards } from '../components/dashboard-metric-cards';
import { DashboardHistoricoCard } from '../components/dashboard-historico-card';
import { DashboardCarteraCard } from '../components/dashboard-cartera-card';
import { EmbeddedServicesStatus } from '../components/embedded-services-status';
import { LoginTrendChartCard } from '../components/login-trend-chart-card';
import { PagosDelDiaCard } from '../components/pagos-del-dia-card';
import { RecentActivityCard } from '../components/recent-activity-card';
import { RecentCompaniesCard } from '../components/recent-companies-card';
import { SuperadminHomeCards } from '../components/superadmin-home-cards';
import type { ApiDashboardSummary } from '../types/api-dashboard-summary';
import type { ApiPanelSuperadmin } from '../types/api-panel-superadmin';
import type { DashboardFiltrosValor } from '../types/dashboard-filtros-valor';
import { buildDashboardEndpoint } from '../utils/build-dashboard-endpoint';

export const HomePage: FC = () => {
    const { user } = useAuth();

    return user?.isSuperAdmin ? <SuperadminHome /> : <CompanyHome />;
};

/** Panel propio del superadmin: tarjetas de plataforma, tendencia de accesos, servicios, bitácora y altas recientes — nunca datos de negocio de una empresa. */
const SuperadminHome: FC = () => {
    const { data: panel, error } = useResource<ApiPanelSuperadmin>('/panel-superadmin');

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Inicio" />

            {error && !panel && (
                <div className="mb-6">
                    <Alert variant="danger" title="No se pudo cargar el panel" message={error} />
                </div>
            )}

            {!panel && !error && <SuperadminHomeSkeletonBody />}

            {panel && (
                <>
                    <div className="mb-6">
                        <SuperadminHomeCards panel={panel} />
                    </div>

                    <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                        <div className="xl:col-span-7">
                            <LoginTrendChartCard tendencia={panel.tendencia_logins} />
                        </div>
                        <div className="xl:col-span-5">
                            <EmbeddedServicesStatus />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-2">
                        <RecentActivityCard />
                        <RecentCompaniesCard empresas={panel.empresas_recientes} />
                    </div>
                </>
            )}
        </div>
    );
};

const CompanyHome: FC = () => {
    const puedeRegistrarPago = usePermission('pagos', 'crear');
    const puedeVerClientes = usePermission('clientes', 'listar');
    const puedeAsignarAsesor = usePermission('clientes', 'crear');

    const [filtros, setFiltros] = useState<DashboardFiltrosValor>({
        periodo: periodoActual(),
        empresaId: null,
        asesorId: null,
        cliente: null,
        estado: null,
    });

    const endpoint = buildDashboardEndpoint(filtros, false);
    const { data: resumen, error } = useResource<ApiDashboardSummary>(endpoint);

    const handleFiltrosChange = (cambios: Partial<DashboardFiltrosValor>) => {
        setFiltros((anterior) => ({ ...anterior, ...cambios }));
    };

    const renderContenido = () => {
        if (error) {
            return (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            );
        }

        if (!resumen) {
            return <DashboardSkeletonBody />;
        }

        return (
            <>
                <DashboardMetricCards metricas={resumen.metricas} />

                {(resumen.pagos_hoy || resumen.metricas.cartera) && (
                    <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                        {resumen.pagos_hoy && (
                            <div className="xl:col-span-7">
                                <PagosDelDiaCard pagos={resumen.pagos_hoy} puedeRegistrarPago={puedeRegistrarPago} />
                            </div>
                        )}

                        <div className="xl:col-span-5">
                            {resumen.metricas.cartera && <DashboardCarteraCard cartera={resumen.metricas.cartera} />}
                        </div>
                    </div>
                )}

                <GoalsDashboardSection />

                <DashboardHistoricoCard historico={resumen.historico} />
            </>
        );
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Inicio" />

            <DashboardFilters
                filtros={filtros}
                onChange={handleFiltrosChange}
                empresas={null}
                mostrarAsesor={puedeAsignarAsesor}
                mostrarCliente={puedeVerClientes}
            />

            {renderContenido()}
        </div>
    );
};
