import type { FC } from 'react';

import { StackedBarChart } from '@ui/charts/components/stacked-bar-chart';
import { DataTable } from '@ui/table/components/data-table';
import { formatMoney } from '../../shared/utils/format-money';
import { MONTHLY_BEHAVIOR_CHART_COLORS } from '../constants/monthly-behavior-chart-colors';
import type { ClientMonthlyBehaviorCardProps } from '../props/client-monthly-behavior-card-props';

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function etiquetaDeMes(mes: string): string {
    const [anio, numeroMes] = mes.split('-').map(Number);

    return `${MESES_CORTOS[numeroMes - 1]} ${String(anio).slice(-2)}`;
}

export const ClientMonthlyBehaviorCard: FC<ClientMonthlyBehaviorCardProps> = ({ comportamiento }) => {
    const { meses, totales } = comportamiento;

    return (
        <section className="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-6">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Comportamiento mensual</h2>
                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    Últimos 12 meses. Una cuota es a tiempo si se completó el día del vencimiento o antes; con mora, si después.
                </p>
            </div>

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">Recaudo en 12 meses</p>
                    <p className="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">{formatMoney(totales.recaudo)}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">Cuotas a tiempo</p>
                    <p className="mt-1 text-xl font-semibold text-success-600 dark:text-success-500">{totales.cuotas_a_tiempo}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">Cuotas con mora</p>
                    <p className="mt-1 text-xl font-semibold text-error-600 dark:text-error-500">{totales.cuotas_con_mora}</p>
                </div>
            </div>

            <StackedBarChart
                categories={meses.map((mes) => etiquetaDeMes(mes.mes))}
                series={[
                    { name: 'A tiempo', data: meses.map((mes) => mes.cuotas_a_tiempo), color: MONTHLY_BEHAVIOR_CHART_COLORS.aTiempo },
                    { name: 'Con mora', data: meses.map((mes) => mes.cuotas_con_mora), color: MONTHLY_BEHAVIOR_CHART_COLORS.conMora },
                ]}
                integerValues
            />

            <div className="mt-6">
                <DataTable
                    columns={[
                        { key: 'mes', label: 'Mes', render: (row) => <span className="whitespace-nowrap">{etiquetaDeMes(row.mes)}</span> },
                        { key: 'recaudo', label: 'Recaudo', render: (row) => <span className="whitespace-nowrap">{formatMoney(row.recaudo)}</span> },
                        { key: 'saldo_cierre', label: 'Saldo al cierre', render: (row) => <span className="whitespace-nowrap">{formatMoney(row.saldo_cierre)}</span> },
                        { key: 'cuotas_a_tiempo', label: 'A tiempo', render: (row) => row.cuotas_a_tiempo },
                        { key: 'cuotas_con_mora', label: 'Con mora', render: (row) => row.cuotas_con_mora },
                    ]}
                    rows={meses}
                    getRowKey={(row) => row.mes}
                    emptyMessage="Sin movimientos en los últimos 12 meses."
                />
            </div>
        </section>
    );
};
