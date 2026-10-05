import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { useAuth } from '../../auth/hooks/use-auth';
import { usePermission } from '../../auth/hooks/use-permission';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import type { ApiCompany } from '../../company/types/api-company';
import { periodoActual } from '../../goals/utils/periodo-meta';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { GoalsDashboardSection } from '../../goals/components/goals-dashboard-section';
import { DashboardFilters } from '../components/dashboard-filters';
import { DashboardMetricCards } from '../components/dashboard-metric-cards';
import { DashboardHistoricoCard } from '../components/dashboard-historico-card';
import { DashboardCarteraCard } from '../components/dashboard-cartera-card';
import { PagosDelDiaCard } from '../components/pagos-del-dia-card';
import type { ApiDashboardSummary } from '../types/api-dashboard-summary';
import type { DashboardFiltrosValor } from '../types/dashboard-filtros-valor';
import { buildDashboardEndpoint } from '../utils/build-dashboard-endpoint';

export const HomePage: FC = () => {
    const { user } = useAuth();
    const esSuperAdmin = user?.isSuperAdmin ?? false;
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

    const endpoint = buildDashboardEndpoint(filtros, esSuperAdmin);
    const { data: resumen, error } = useResource<ApiDashboardSummary>(endpoint);
    const { data: empresas } = useResource<PaginatedResponse<ApiCompany>>(esSuperAdmin ? '/empresas?per_page=100' : null);

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

        if (endpoint === null) {
            return (
                <p className="rounded-2xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400">
                    Elige una empresa para ver su dashboard.
                </p>
            );
        }

        if (!resumen) {
            return (
                <div className="flex justify-center py-12">
                    <Spinner size="lg" />
                </div>
            );
        }

        return (
            <>
                <DashboardMetricCards metricas={resumen.metricas} />

                <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <PagosDelDiaCard pagos={resumen.pagos_hoy} puedeRegistrarPago={puedeRegistrarPago} />
                    </div>

                    <div className="xl:col-span-5">
                        {resumen.metricas.cartera && <DashboardCarteraCard cartera={resumen.metricas.cartera} />}
                    </div>
                </div>

                {!esSuperAdmin && <GoalsDashboardSection />}

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
                empresas={esSuperAdmin ? (empresas?.data ?? []) : null}
                mostrarAsesor={puedeAsignarAsesor}
                mostrarCliente={puedeVerClientes}
            />

            {renderContenido()}
        </div>
    );
};
