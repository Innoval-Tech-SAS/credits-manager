import type { FC } from 'react';

import { formatMoney } from '../../shared/utils/format-money';
import type { CreditSummaryCardsProps } from '../props/credit-summary-cards-props';

export const CreditSummaryCards: FC<CreditSummaryCardsProps> = ({ valorPrestamo, valorTotal, saldo, cartera }) => (
    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Valor prestado</p>
            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">{formatMoney(valorPrestamo)}</p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Valor total a pagar</p>
            <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">{formatMoney(valorTotal)}</p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Saldo pendiente</p>
            <p className="mt-1 text-title-sm font-semibold text-brand-600 dark:text-brand-400">{formatMoney(saldo)}</p>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p className="text-sm text-gray-500 dark:text-gray-400">Estado de cartera</p>
            <p
                className={`mt-1 text-title-sm font-semibold ${
                    cartera === 'En mora' ? 'text-error-600 dark:text-error-400' : 'text-success-600 dark:text-success-400'
                }`}
            >
                {cartera}
            </p>
        </div>
    </div>
);
