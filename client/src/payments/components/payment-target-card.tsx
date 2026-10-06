import type { FC } from 'react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import type { PaymentTargetCardProps } from '../props/payment-target-card-props';

export const PaymentTargetCard: FC<PaymentTargetCardProps> = ({ cuota }) => {
    const saldo = parseFloat(cuota.valor_total_cuota) - parseFloat(cuota.valor_pagado);

    return (
        <div className="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-800 dark:bg-brand-500/10">
            <p className="text-sm font-medium text-brand-600 dark:text-brand-400">Vas a pagar la cuota #{cuota.numero_cuota}</p>
            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Vence</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatDateEs(cuota.fecha_vencimiento)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Total de la cuota</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(cuota.valor_total_cuota)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Saldo pendiente de la cuota</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(saldo.toFixed(2))}</p>
                </div>
            </div>
            <div className="mt-4 grid grid-cols-1 gap-4 border-t border-brand-200 pt-4 sm:grid-cols-3 dark:border-brand-800">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Capital de la cuota</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(cuota.valor_capital)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Intereses</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(cuota.valor_interes)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Otros gastos</p>
                    <p className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(cuota.valor_otros_gastos)}</p>
                </div>
            </div>
        </div>
    );
};
