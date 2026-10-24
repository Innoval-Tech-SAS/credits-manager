import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { ArrowRight } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { DonutChart } from '@ui/charts/components/donut-chart';
import { formatMoney } from '../../shared/utils/format-money';
import type { DashboardCarteraCardProps } from '../props/dashboard-cartera-card-props';
import { DASHBOARD_CHART_COLORS } from '../constants/dashboard-chart-colors';

export const DashboardCarteraCard: FC<DashboardCarteraCardProps> = ({ cartera }) => {
    const navigate = useNavigate();
    const sinSaldo = Number(cartera.pendiente) === 0;

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Estado de cartera</h2>
            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Saldo pendiente de cuotas, al día frente a vencido.</p>

            {sinSaldo ? (
                <p className="py-16 text-center text-sm text-gray-500 dark:text-gray-400">No hay saldo pendiente con este filtro.</p>
            ) : (
                <DonutChart
                    series={[Number(cartera.al_dia), Number(cartera.vencida)]}
                    labels={['Al día', 'Vencida']}
                    colors={[DASHBOARD_CHART_COLORS.alDia, DASHBOARD_CHART_COLORS.vencida]}
                    totalLabel="Pendiente"
                    totalValue={formatMoney(cartera.pendiente)}
                    valueFormatter={(valor) => formatMoney(valor)}
                />
            )}

            <div className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <Button size="sm" variant="outline" endIcon={<ArrowRight className="size-4" />} onClick={() => navigate('/cartera')}>
                    Ver cartera completa
                </Button>
            </div>
        </section>
    );
};
