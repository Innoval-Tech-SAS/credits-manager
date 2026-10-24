import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { CARTERA_AGING_BUCKETS } from '../constants/cartera-aging-buckets';
import type { CarteraAgingStripProps } from '../props/cartera-aging-strip-props';

export const CarteraAgingStrip: FC<CarteraAgingStripProps> = ({ edades, isLoading }) => (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {CARTERA_AGING_BUCKETS.map(({ key, label }) => (
            <div key={key} className="rounded-xl border border-gray-200 bg-white p-3 text-center dark:border-gray-800 dark:bg-white/3">
                <p className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">{label}</p>
                <p className="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">
                    {isLoading || !edades ? '—' : formatMoney(edades[key])}
                </p>
            </div>
        ))}
    </div>
);
