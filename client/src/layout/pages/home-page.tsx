import type { FC } from 'react';
import { Navigate, useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { useAuth } from '../../auth/hooks/use-auth';
import { usePermission } from '../../auth/hooks/use-permission';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { ArrowRight, CreditCard, HandCoins, TriangleAlert, Users, Wallet } from 'lucide-react';
import { Spinner } from '@ui/spinner/components/spinner';
import { DataTable } from '@ui/table/components/data-table';
import { DonutChart } from '@ui/charts/components/donut-chart';
import { StatCard } from '../components/stat-card';
import { GoalsDashboardSection } from '../../goals/components/goals-dashboard-section';
import type { ApiDashboardSummary } from '../types/api-dashboard-summary';

export const HomePage: FC = () => {
    const navigate = useNavigate();
    const { user } = useAuth();
    const canRegisterPayment = usePermission('pagos', 'crear');

    const { data: resumen, error } = useResource<ApiDashboardSummary>('/dashboard');

    if (user?.isSuperAdmin) {
        return <Navigate to="/empresas" replace />;
    }

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    if (!resumen) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const creditosAlDia = Math.max(0, resumen.creditos_activos - resumen.creditos_en_mora);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Inicio" />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                <StatCard label="Clientes activos" value={String(resumen.clientes_activos)} icon={Users} />
                <StatCard label="Créditos activos" value={String(resumen.creditos_activos)} icon={CreditCard} />
                <StatCard label="Cartera pendiente" value={formatMoney(resumen.cartera_pendiente)} icon={Wallet} accent="success" />
                <StatCard label="Créditos en mora" value={String(resumen.creditos_en_mora)} icon={TriangleAlert} accent="error" />
            </div>

            <GoalsDashboardSection />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3 xl:col-span-2">
                    <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Pagos del día</h2>

                    <DataTable
                        columns={[
                            { key: 'cliente_nombre', label: 'Cliente' },
                            { key: 'numero_cuota', label: 'Cuota #' },
                            { key: 'valor', label: 'Valor', render: (row) => formatMoney(row.valor) },
                            { key: 'pendiente', label: 'Pendiente', render: (row) => formatMoney(row.pendiente) },
                            {
                                key: 'acciones',
                                label: '',
                                render: (row) =>
                                    canRegisterPayment ? (
                                        <div className="text-right">
                                            <Button collapseBelow="md" startIcon={<HandCoins className="size-4" />} size="sm" variant="outline" onClick={() => navigate(`/creditos/${row.credito_id}/pagos/nuevo`)}>
                                                Registrar pago
                                            </Button>
                                        </div>
                                    ) : null,
                            },
                        ]}
                        rows={resumen.pagos_hoy}
                        getRowKey={(row) => row.cuota_id}
                        emptyMessage="No hay cuotas con vencimiento hoy."
                    />

                    <div className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <Button iconOnly size="sm" variant="outline" endIcon={<ArrowRight className="size-4" />} onClick={() => navigate('/calendario')}>
                            Ver calendario completo
                        </Button>
                    </div>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Estado de cartera</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Créditos activos al día vs. en mora.</p>

                    <DonutChart
                        series={[creditosAlDia, resumen.creditos_en_mora]}
                        labels={['Al día', 'En mora']}
                        colors={['#12b76a', '#ef4444']}
                        totalLabel="Activos"
                        totalValue={String(resumen.creditos_activos)}
                    />

                    <Button iconOnly
                        size="sm"
                        variant="outline"
                        className="mt-2"
                        endIcon={<ArrowRight className="size-4" />}
                        onClick={() => navigate('/cartera')}
                    >
                        Ver cartera completa
                    </Button>
                </div>
            </div>
        </div>
    );
};
