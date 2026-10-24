import type { FC } from 'react';
import { CircleCheck, TriangleAlert } from 'lucide-react';

import { formatMoney } from '../../shared/utils/format-money';
import { DonutChart } from '@ui/charts/components/donut-chart';
import { AGING_BUCKETS } from '../constants/aging-buckets';
import type { PortfolioSummaryCardsProps } from '../props/portfolio-summary-cards-props';

export const PortfolioSummaryCards: FC<PortfolioSummaryCardsProps> = ({ totales, envejecimientoMora, accion }) => {
    const carteraTotal = Number(totales.al_dia.saldo) + Number(totales.en_mora.saldo);

    return (
        <div className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Distribución de cartera</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Saldo pendiente por estado de mora.</p>
                    </div>
                    {accion}
                </div>

                <DonutChart
                    series={[Number(totales.al_dia.saldo), Number(totales.en_mora.saldo)]}
                    labels={['Al día', 'En mora']}
                    colors={['#12b76a', '#ef4444']}
                    totalLabel="Cartera total"
                    totalValue={formatMoney(carteraTotal)}
                    valueFormatter={formatMoney}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:col-span-2">
                <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400">
                        <CircleCheck className="size-6" />
                    </div>
                    <div className="mt-5">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Al día</p>
                        <p className="mt-1 text-title-sm font-bold text-success-600 dark:text-success-400">{totales.al_dia.count} créditos</p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{formatMoney(totales.al_dia.saldo)}</p>
                    </div>
                    <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                        <p className="text-xs text-gray-400 dark:text-gray-500">Promedio por crédito</p>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{formatMoney(totales.al_dia.promedio)}</p>
                    </div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                        <TriangleAlert className="size-6" />
                    </div>
                    <div className="mt-5">
                        <p className="text-sm text-gray-500 dark:text-gray-400">En mora</p>
                        <p className="mt-1 text-title-sm font-bold text-error-600 dark:text-error-400">{totales.en_mora.count} créditos</p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{formatMoney(totales.en_mora.saldo)}</p>
                    </div>
                    <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                        <p className="text-xs text-gray-400 dark:text-gray-500">Promedio por crédito</p>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{formatMoney(totales.en_mora.promedio)}</p>
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-x-2 gap-y-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                        {AGING_BUCKETS.map(({ key, label }) => (
                            <div key={key} className="text-center">
                                <p className="text-[10px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">{label}</p>
                                <p className="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{envejecimientoMora[key].count}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">{formatMoney(envejecimientoMora[key].saldo)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};
