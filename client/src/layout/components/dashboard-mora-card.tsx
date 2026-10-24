import type { FC } from 'react';
import { TriangleAlert } from 'lucide-react';

import { AGING_BUCKETS } from '../../portfolio/constants/aging-buckets';
import { formatMoney } from '../../shared/utils/format-money';
import type { DashboardMoraCardProps } from '../props/dashboard-mora-card-props';

export const DashboardMoraCard: FC<DashboardMoraCardProps> = ({ mora }) => {
    const rangos = AGING_BUCKETS.map((bucket) => ({
        ...bucket,
        fila: mora.rangos.find((rango) => rango.clave === bucket.key),
    }));

    return (
        <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
            <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:items-center md:gap-10">
                <div className="flex flex-col gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                        <TriangleAlert className="size-6" />
                    </div>

                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">En mora</span>
                        <h4 className="mt-1 text-title-sm font-bold text-error-500 dark:text-error-400">{mora.creditos} créditos</h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{formatMoney(mora.saldo)}</p>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <span className="text-sm text-gray-500 dark:text-gray-400">Promedio por crédito</span>
                        <span className="text-sm font-semibold text-gray-800 dark:text-white/90">{formatMoney(mora.promedio)}</span>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-x-6 gap-y-5 border-gray-100 md:grid-cols-4 md:border-l md:pl-10 dark:border-gray-800">
                    {rangos.map(({ key, label, fila }) => (
                        <div key={key} className="text-center md:text-left">
                            <p className="text-[11px] font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{label}</p>
                            <p className="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">{fila?.creditos ?? 0}</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{formatMoney(fila?.saldo ?? '0')}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
};
