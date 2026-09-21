import type { FC } from 'react';
import { Link } from 'react-router';
import { ArrowRightLeft } from 'lucide-react';

import { formatMoney } from '../../shared/utils/format-money';
import type { RefinancingNoticeProps } from '../props/refinancing-notice-props';

export const RefinancingNotice: FC<RefinancingNoticeProps> = ({ vinculo, valorPrestamo, linkSuffix = '', className = '' }) => {
    const { id: relacionadoId, estado: relacionadoEstado } = vinculo.credito_relacionado;
    const esRefinanciacion = vinculo.tipo === 'refinancia';
    const originalCongelado = relacionadoEstado === 'refinanciado';

    const titulo = esRefinanciacion ? 'Refinanciación' : 'Crédito refinanciado';
    const descripcion = esRefinanciacion
        ? originalCongelado
            ? 'Este crédito refinanció al crédito original, que quedó congelado como historial. Los pagos se aplican a este crédito.'
            : 'Este crédito refinancia al crédito original, que quedará congelado como historial cuando se desembolse este crédito.'
        : 'Este crédito quedó congelado como historial. Los pagos se aplican a la refinanciación que lo reemplazó.';

    return (
        <div className={`rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 ${className}`}>
            <div className="flex items-start gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400 print:hidden">
                    <ArrowRightLeft className="size-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-gray-800 dark:text-white/90">{titulo}</p>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{descripcion}</p>

                    {esRefinanciacion && vinculo.saldo_tomado != null && vinculo.valor_adicional != null && (
                        <dl className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Saldo de capital tomado</dt>
                                <dd className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(vinculo.saldo_tomado)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Nuevo valor prestado</dt>
                                <dd className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(vinculo.valor_adicional)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500 dark:text-gray-400">Valor de este crédito</dt>
                                <dd className="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(valorPrestamo)}</dd>
                            </div>
                        </dl>
                    )}

                    <Link
                        to={`/creditos/${relacionadoId}${linkSuffix}`}
                        className="mt-4 inline-block text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400 print:hidden"
                    >
                        {esRefinanciacion ? 'Ver crédito original' : 'Ver refinanciación'}
                    </Link>
                </div>
            </div>
        </div>
    );
};
