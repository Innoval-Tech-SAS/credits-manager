import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { Calendar, CircleDollarSign, CreditCard, HandCoins } from 'lucide-react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import type { ActiveCreditCardProps } from '../props/active-credit-card-props';

export const ActiveCreditCard: FC<ActiveCreditCardProps> = ({ credito }) => {
    const navigate = useNavigate();
    const enMora = (credito.cuotas_vencidas ?? 0) > 0;

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span className="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <CreditCard className="size-4" />
                    </span>
                    <p className="font-semibold text-gray-800 dark:text-white/90">Crédito #{credito.id}</p>
                </div>

                <span
                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        enMora
                            ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                            : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                    }`}
                >
                    {enMora ? 'En mora' : 'Al día'}
                </span>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Valor prestado</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(credito.valor_prestamo)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Valor cuota</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(credito.valor_cuota_total)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Valor total a pagar</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(credito.valor_total_con_otros)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Tasa de interés</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{parseFloat(credito.tasa_interes).toFixed(3)}%</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Plazo</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">
                        {credito.plazo} cuotas ({credito.tipo_pago === 'mensual' ? 'mensual' : 'quincenal'})
                    </p>
                </div>
                <div>
                    <p className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <Calendar className="size-3.5" /> Desembolso
                    </p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatDateEs(credito.fecha_desembolso)}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Pagos realizados</p>
                    <p className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{credito.pagos_count ?? 0}</p>
                </div>
            </div>

            <div className="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                <Button iconOnly size="sm" startIcon={<HandCoins className="size-4" />} onClick={() => navigate(`/pagos/creditos/${credito.id}/nuevo`)}>
                    Pagar cuota
                </Button>
                <Button iconOnly startIcon={<CircleDollarSign className="size-4" />} size="sm" variant="outline" onClick={() => navigate(`/pagos/creditos/${credito.id}/liquidar`)}>
                    Liquidar
                </Button>
                <button
                    type="button"
                    onClick={() => navigate(`/pagos/creditos/${credito.id}`)}
                    className="ml-auto text-sm text-brand-500 hover:underline dark:text-brand-400"
                >
                    Ver pagos
                </button>
            </div>
        </div>
    );
};
