import type { FC } from 'react';
import { useState } from 'react';
import { useParams } from 'react-router';
import { Building2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { periodoActual } from '../../goals/utils/periodo-meta';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DashboardSkeletonBody } from '@ui/skeleton/components/dashboard-skeleton-body';
import { DashboardFilters } from '../../layout/components/dashboard-filters';
import { DashboardMetricCards } from '../../layout/components/dashboard-metric-cards';
import { DashboardHistoricoCard } from '../../layout/components/dashboard-historico-card';
import { DashboardCarteraCard } from '../../layout/components/dashboard-cartera-card';
import { PagosDelDiaCard } from '../../layout/components/pagos-del-dia-card';
import type { ApiDashboardSummary } from '../../layout/types/api-dashboard-summary';
import type { DashboardFiltrosValor } from '../../layout/types/dashboard-filtros-valor';
import { buildDashboardEndpoint } from '../../layout/utils/build-dashboard-endpoint';
import type { ApiCompany } from '../types/api-company';

/** Dashboard de una empresa vista por el superadmin: mismo contenido que ve la propia empresa, sin selector (la empresa la fija la ruta). */
export const CompanyDashboardPage: FC = () => {
    const { id } = useParams();
    const empresaId = Number(id);

    const { data: empresaData } = useResource<{ empresa: ApiCompany }>(`/empresas/${empresaId}`);
    const empresa = empresaData?.empresa;

    const [filtros, setFiltros] = useState<DashboardFiltrosValor>({
        periodo: periodoActual(),
        empresaId,
        asesorId: null,
        cliente: null,
        estado: null,
    });

    const endpoint = buildDashboardEndpoint(filtros, true);
    const { data: resumen, error } = useResource<ApiDashboardSummary>(endpoint);

    const handleFiltrosChange = (cambios: Partial<DashboardFiltrosValor>) => {
        setFiltros((anterior) => ({ ...anterior, ...cambios }));
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={empresa?.nombre_empresa ?? 'Dashboard de la empresa'} trail={[{ name: 'Empresas', icon: Building2 }]} />

            <DashboardFilters filtros={filtros} onChange={handleFiltrosChange} empresas={null} mostrarAsesor={false} mostrarCliente={false} />

            {error && (
                <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                    {error}
                </div>
            )}

            {!resumen && !error && <DashboardSkeletonBody />}

            {resumen && (
                <>
                    <DashboardMetricCards metricas={resumen.metricas} />

                    {(resumen.pagos_hoy || resumen.metricas.cartera) && (
                        <div className="mb-6 grid grid-cols-1 gap-4 md:gap-6 xl:grid-cols-12">
                            {resumen.pagos_hoy && (
                                <div className="xl:col-span-7">
                                    <PagosDelDiaCard pagos={resumen.pagos_hoy} puedeRegistrarPago={false} />
                                </div>
                            )}

                            <div className="xl:col-span-5">
                                {resumen.metricas.cartera && <DashboardCarteraCard cartera={resumen.metricas.cartera} />}
                            </div>
                        </div>
                    )}

                    <DashboardHistoricoCard historico={resumen.historico} />
                </>
            )}
        </div>
    );
};
