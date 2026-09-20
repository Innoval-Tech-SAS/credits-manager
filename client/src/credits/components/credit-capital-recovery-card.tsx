import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import { RadialGaugeChart } from '@ui/charts/components/radial-gauge-chart';
import type { CreditCapitalRecoveryCardProps } from '../props/credit-capital-recovery-card-props';

export const CreditCapitalRecoveryCard: FC<CreditCapitalRecoveryCardProps> = ({ porcentaje, resumenCuotas }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Capital recuperado</h2>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Del valor prestado ya cubierto por los pagos.</p>

        <RadialGaugeChart percentage={porcentaje} color="#465fff" />

        <p className="mx-auto -mt-2 max-w-[280px] text-center text-sm text-gray-500 dark:text-gray-400">
            Ya cubriste el {porcentaje.toFixed(0)}% del capital prestado. Quedan{' '}
            <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(resumenCuotas.saldo_capital_pendiente)}</span>{' '}
            de capital por cobrar.
        </p>

        <div className="mt-6 grid grid-cols-3 divide-x divide-gray-100 border-t border-gray-100 pt-4 dark:divide-gray-800 dark:border-gray-800">
            <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Pagado</p>
                <p className="mt-1 text-sm font-semibold text-success-600 dark:text-success-400">
                    {formatMoney(resumenCuotas.saldo_capital_pagado)}
                </p>
            </div>
            <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Capital pend.</p>
                <p className="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">
                    {formatMoney(resumenCuotas.saldo_capital_pendiente)}
                </p>
            </div>
            <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Interés pend.</p>
                <p className="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">
                    {formatMoney(resumenCuotas.interes_pendiente)}
                </p>
            </div>
        </div>
    </div>
);
